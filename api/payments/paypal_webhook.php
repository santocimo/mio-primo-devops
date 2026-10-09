<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../inc/subscription_plans.php';
require_once __DIR__ . '/../../inc/recurring_subscriptions.php';

header('Content-Type: application/json');

function paypal_webhook_fail(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function paypal_webhook_request(string $method, string $url, array $headers, ?string $body = null): array {
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    }
    $raw = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if (!is_string($raw)) {
        $error = curl_error($curl);
        curl_close($curl);
        error_log('PayPal webhook request failed: ' . $error);
        throw new RuntimeException('PayPal request failed');
    }
    curl_close($curl);
    $response = $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($response)) {
        throw new RuntimeException('PayPal returned an invalid response');
    }
    return [$status, $response];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    paypal_webhook_fail(405, 'Method not allowed');
}

$clientId = getenv('PAYPAL_CLIENT_ID') ?: '';
$clientSecret = getenv('PAYPAL_CLIENT_SECRET') ?: '';
$webhookId = getenv('PAYPAL_WEBHOOK_ID') ?: '';
if ($clientId === '' || $clientSecret === '' || $webhookId === '') {
    paypal_webhook_fail(503, 'PayPal webhook is not configured');
}
$base = getenv('PAYPAL_MODE') === 'live'
    ? 'https://api-m.paypal.com'
    : 'https://api-m.sandbox.paypal.com';
$event = json_decode(file_get_contents('php://input'), true);
if (!is_array($event) || empty($event['event_type'])) {
    paypal_webhook_fail(400, 'Invalid webhook event');
}

try {
    [$tokenStatus, $token] = paypal_webhook_request(
        'POST',
        "$base/v1/oauth2/token",
        [
            'Authorization: Basic ' . base64_encode("$clientId:$clientSecret"),
            'Content-Type: application/x-www-form-urlencoded',
        ],
        'grant_type=client_credentials'
    );
    if ($tokenStatus !== 200 || empty($token['access_token'])) {
        paypal_webhook_fail(502, 'PayPal authentication failed');
    }
    $accessToken = (string)$token['access_token'];
    $verifyPayload = [
        'auth_algo' => $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] ?? '',
        'cert_url' => $_SERVER['HTTP_PAYPAL_CERT_URL'] ?? '',
        'transmission_id' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] ?? '',
        'transmission_sig' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] ?? '',
        'transmission_time' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] ?? '',
        'webhook_id' => $webhookId,
        'webhook_event' => $event,
    ];
    [$verifyStatus, $verification] = paypal_webhook_request(
        'POST',
        "$base/v1/notifications/verify-webhook-signature",
        [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        json_encode($verifyPayload, JSON_THROW_ON_ERROR)
    );
    if ($verifyStatus >= 300 || ($verification['verification_status'] ?? '') !== 'SUCCESS') {
        paypal_webhook_fail(400, 'Invalid PayPal webhook signature');
    }

    $pdo = getPDO();
    $plans = subscription_plans();
    $eventType = (string)$event['event_type'];
    $resource = $event['resource'] ?? [];

    if (in_array($eventType, [
        'BILLING.SUBSCRIPTION.ACTIVATED',
        'BILLING.SUBSCRIPTION.UPDATED',
        'BILLING.SUBSCRIPTION.CANCELLED',
        'BILLING.SUBSCRIPTION.SUSPENDED',
        'BILLING.SUBSCRIPTION.EXPIRED',
    ], true)) {
        $subscriptionId = (string)($resource['id'] ?? '');
        $status = [
            'BILLING.SUBSCRIPTION.ACTIVATED' => 'active',
            'BILLING.SUBSCRIPTION.UPDATED' => strtolower((string)($resource['status'] ?? 'active')),
            'BILLING.SUBSCRIPTION.CANCELLED' => 'canceled',
            'BILLING.SUBSCRIPTION.SUSPENDED' => 'suspended',
            'BILLING.SUBSCRIPTION.EXPIRED' => 'expired',
        ][$eventType];
        if ($eventType === 'BILLING.SUBSCRIPTION.UPDATED' && !in_array($status, ['active', 'suspended', 'canceled', 'expired'], true)) {
            paypal_webhook_fail(400, 'Unsupported PayPal subscription status');
        }
        $known = find_gym_subscription_by_provider_id($pdo, 'paypal', $subscriptionId);
        if (!$known) {
            [$detailStatus, $details] = paypal_webhook_request(
                'GET',
                "$base/v1/billing/subscriptions/" . rawurlencode($subscriptionId),
                ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json']
            );
            if ($detailStatus >= 300) {
                paypal_webhook_fail(502, 'Could not retrieve PayPal subscription');
            }
            [$gymId, $ownerId, $plan] = array_pad(
                explode('|', (string)($details['custom_id'] ?? ''), 3),
                3,
                ''
            );
            $expectedPlanId = getenv('PAYPAL_PLAN_' . strtoupper($plan) . '_ID') ?: '';
            if ((int)$gymId <= 0 || !isset($plans[$plan]) || $expectedPlanId === ''
                || ($details['plan_id'] ?? '') !== $expectedPlanId
                || !is_gym_billing_owner($pdo, (int)$ownerId, (int)$gymId)) {
                paypal_webhook_fail(400, 'PayPal subscription details could not be verified');
            }
            $resource = $details;
            $resource['status'] = strtoupper($status);
        } else {
            $gymId = (int)$known['gym_id'];
            $plan = (string)$known['plan'];
            $details = $resource;
        }
        $periodEnd = strtotime((string)($details['billing_info']['next_billing_time'] ?? ''));
        save_gym_recurring_subscription(
            $pdo,
            (int)$gymId,
            'paypal',
            $subscriptionId,
            (string)$plan,
            $status,
            $periodEnd ? date('Y-m-d H:i:s', $periodEnd) : ($known['current_period_end'] ?? null),
            (string)($details['subscriber']['payer_id'] ?? $known['provider_customer_id'] ?? ''),
            in_array($status, ['canceled', 'expired'], true)
        );
    } elseif (in_array($eventType, ['PAYMENT.SALE.COMPLETED', 'PAYMENT.SALE.DENIED'], true)) {
        $subscriptionId = (string)($resource['billing_agreement_id'] ?? '');
        $subscription = find_gym_subscription_by_provider_id($pdo, 'paypal', $subscriptionId);
        if (!$subscription) {
            paypal_webhook_fail(400, 'Unknown PayPal recurring payment');
        }
        if ($eventType === 'PAYMENT.SALE.DENIED') {
            $update = $pdo->prepare("UPDATE gym_subscriptions SET status = 'past_due' WHERE gym_id = ?");
            $update->execute([(int)$subscription['gym_id']]);
        } else {
            $amount = (string)($resource['amount']['total'] ?? '');
            $currency = strtoupper((string)($resource['amount']['currency'] ?? ''));
            if ($amount !== $plans[$subscription['plan']]['price'] || $currency !== $plans['_currency']) {
                paypal_webhook_fail(400, 'PayPal recurring payment amount could not be verified');
            }
            record_gym_subscription_payment(
                $pdo,
                'paypal',
                (string)($resource['id'] ?? ''),
                (int)$subscription['gym_id'],
                (string)$subscription['plan'],
                $plans[$subscription['plan']]['amount_minor'],
                $currency
            );
            [$detailStatus, $details] = paypal_webhook_request(
                'GET',
                "$base/v1/billing/subscriptions/" . rawurlencode($subscriptionId),
                ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json']
            );
            if ($detailStatus >= 300) {
                paypal_webhook_fail(502, 'Could not retrieve renewed PayPal subscription');
            }
            $nextBillingTime = strtotime((string)($details['billing_info']['next_billing_time'] ?? ''));
            if ($nextBillingTime) {
                save_gym_recurring_subscription(
                    $pdo,
                    (int)$subscription['gym_id'],
                    'paypal',
                    $subscriptionId,
                    (string)$subscription['plan'],
                    strtolower((string)($details['status'] ?? 'active')),
                    date('Y-m-d H:i:s', $nextBillingTime),
                    (string)($details['subscriber']['payer_id'] ?? $subscription['provider_customer_id'] ?? ''),
                    !empty($subscription['cancel_at_period_end'])
                );
            }
        }
    }

    echo json_encode(['success' => true, 'received' => true]);
} catch (Throwable $e) {
    error_log('PayPal webhook processing failed: ' . $e->getMessage());
    paypal_webhook_fail(500, 'Webhook processing failed');
}
