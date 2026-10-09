<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../inc/subscription_plans.php';
require_once __DIR__ . '/../../inc/recurring_subscriptions.php';
require_once __DIR__ . '/../../inc/stripe_webhook.php';

header('Content-Type: application/json');

function stripe_webhook_fail(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function stripe_webhook_request(string $path, string $secret): array {
    $curl = curl_init('https://api.stripe.com' . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secret],
    ]);
    $raw = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if (!is_string($raw)) {
        $error = curl_error($curl);
        curl_close($curl);
        error_log('Stripe webhook lookup failed: ' . $error);
        throw new RuntimeException('Stripe lookup failed');
    }
    curl_close($curl);
    $response = json_decode($raw, true);
    if (!is_array($response)) {
        throw new RuntimeException('Stripe returned an invalid response');
    }
    return [$status, $response];
}

function stripe_subscription_period_end(array $subscription): ?string {
    $end = (int)($subscription['current_period_end'] ?? 0);
    return $end > 0 ? date('Y-m-d H:i:s', $end) : null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    stripe_webhook_fail(405, 'Method not allowed');
}

$secret = getenv('STRIPE_WEBHOOK_SECRET') ?: '';
$apiSecret = getenv('STRIPE_SECRET_KEY') ?: '';
if ($secret === '' || $apiSecret === '') {
    stripe_webhook_fail(503, 'Webhook is not configured');
}
$signatureHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$rawBody = file_get_contents('php://input');
if (!verify_stripe_webhook_signature($rawBody, $signatureHeader, $secret)) {
    stripe_webhook_fail(400, 'Invalid webhook signature');
}

$event = json_decode($rawBody, true);
if (!is_array($event) || !isset($event['type'], $event['data']['object'])) {
    stripe_webhook_fail(400, 'Invalid webhook event');
}

$type = (string)$event['type'];
$object = $event['data']['object'];
$supported = [
    'checkout.session.completed',
    'checkout.session.async_payment_succeeded',
    'customer.subscription.created',
    'customer.subscription.updated',
    'customer.subscription.deleted',
    'invoice.paid',
    'invoice.payment_succeeded',
    'invoice.payment_failed',
];
if (!in_array($type, $supported, true)) {
    echo json_encode(['success' => true, 'received' => true]);
    exit;
}

try {
    $pdo = getPDO();
    $plans = subscription_plans();

    if (str_starts_with($type, 'checkout.session.')) {
        if (($object['mode'] ?? '') !== 'subscription'
            || !in_array($object['payment_status'] ?? '', ['paid', 'no_payment_required'], true)) {
            echo json_encode(['success' => true, 'received' => true]);
            exit;
        }
        $subscriptionId = (string)($object['subscription'] ?? '');
        $gymId = (int)($object['metadata']['gym_id'] ?? 0);
        $ownerId = (int)($object['metadata']['user_id'] ?? 0);
        $plan = (string)($object['metadata']['plan'] ?? '');
        if ($subscriptionId === '' || $gymId <= 0 || !isset($plans[$plan])
            || (int)($object['client_reference_id'] ?? 0) !== $ownerId
            || !is_gym_billing_owner($pdo, $ownerId, $gymId)) {
            stripe_webhook_fail(400, 'Checkout subscription details could not be verified');
        }
        [$status, $subscription] = stripe_webhook_request(
            '/v1/subscriptions/' . rawurlencode($subscriptionId),
            $apiSecret
        );
        $item = $subscription['items']['data'][0] ?? [];
        $price = $item['price'] ?? [];
        if ($status >= 300
            || ($subscription['status'] ?? '') !== 'active'
            || (int)($subscription['metadata']['gym_id'] ?? 0) !== $gymId
            || (int)($subscription['metadata']['user_id'] ?? 0) !== $ownerId
            || (string)($subscription['metadata']['plan'] ?? '') !== $plan
            || (int)($price['unit_amount'] ?? 0) !== $plans[$plan]['amount_minor']
            || strtoupper((string)($price['currency'] ?? '')) !== $plans['_currency']
            || ($price['recurring']['interval'] ?? '') !== $plans[$plan]['interval']) {
            stripe_webhook_fail(400, 'Recurring subscription details could not be verified');
        }
        save_gym_recurring_subscription(
            $pdo,
            $gymId,
            'stripe',
            $subscriptionId,
            $plan,
            (string)$subscription['status'],
            stripe_subscription_period_end($subscription),
            (string)($subscription['customer'] ?? ''),
            !empty($subscription['cancel_at_period_end'])
        );
    } elseif (str_starts_with($type, 'customer.subscription.')) {
        $subscriptionId = (string)($object['id'] ?? '');
        $gymId = (int)($object['metadata']['gym_id'] ?? 0);
        $ownerId = (int)($object['metadata']['user_id'] ?? 0);
        $plan = (string)($object['metadata']['plan'] ?? '');
        $status = (string)($object['status'] ?? '');
        $item = $object['items']['data'][0] ?? [];
        $price = $item['price'] ?? [];
        if ($subscriptionId === '' || $gymId <= 0 || !isset($plans[$plan])
            || !is_gym_billing_owner($pdo, $ownerId, $gymId)
            || (int)($price['unit_amount'] ?? 0) !== $plans[$plan]['amount_minor']
            || strtoupper((string)($price['currency'] ?? '')) !== $plans['_currency']
            || ($price['recurring']['interval'] ?? '') !== $plans[$plan]['interval']) {
            stripe_webhook_fail(400, 'Recurring subscription details could not be verified');
        }
        save_gym_recurring_subscription(
            $pdo,
            $gymId,
            'stripe',
            $subscriptionId,
            $plan,
            $status,
            stripe_subscription_period_end($object),
            (string)($object['customer'] ?? ''),
            !empty($object['cancel_at_period_end'])
        );
    } else {
        $subscriptionId = (string)($object['subscription'] ?? $object['parent']['subscription_details']['subscription'] ?? '');
        $subscription = $subscriptionId !== ''
            ? find_gym_subscription_by_provider_id($pdo, 'stripe', $subscriptionId)
            : null;
        if (!$subscription) {
            stripe_webhook_fail(400, 'Unknown recurring subscription invoice');
        }
        if ($type === 'invoice.payment_failed') {
            $update = $pdo->prepare("UPDATE gym_subscriptions SET status = 'past_due' WHERE gym_id = ?");
            $update->execute([(int)$subscription['gym_id']]);
        } else {
            $amount = (int)($object['amount_paid'] ?? 0);
            $currency = strtoupper((string)($object['currency'] ?? ''));
            if ($amount > 0) {
                record_gym_subscription_payment(
                    $pdo,
                    'stripe',
                    (string)$object['id'],
                    (int)$subscription['gym_id'],
                    (string)$subscription['plan'],
                    $amount,
                    $currency
                );
            }
            $periodEnd = (int)($object['lines']['data'][0]['period']['end'] ?? 0);
            if ($periodEnd > 0) {
                $update = $pdo->prepare(
                    "UPDATE gym_subscriptions
                     SET current_period_end = ?,
                         status = CASE WHEN cancel_at_period_end = 1 THEN 'canceled' ELSE 'active' END
                     WHERE gym_id = ?"
                );
                $update->execute([date('Y-m-d H:i:s', $periodEnd), (int)$subscription['gym_id']]);
            }
        }
    }

    echo json_encode(['success' => true, 'received' => true]);
} catch (Throwable $e) {
    error_log('Stripe webhook processing failed: ' . $e->getMessage());
    stripe_webhook_fail(500, 'Webhook processing failed');
}
