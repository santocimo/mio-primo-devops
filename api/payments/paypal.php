<?php
require_once __DIR__ . '/../../inc/security.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth/verify_token.php';
require_once __DIR__ . '/../../inc/subscription_plans.php';
require_once __DIR__ . '/../../inc/recurring_subscriptions.php';
require_once __DIR__ . '/../../inc/subscription.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

function paypal_fail(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function paypal_request(string $method, string $url, array $headers, ?string $body = null): array {
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
        error_log('PayPal request failed: ' . $error);
        throw new RuntimeException('PayPal request failed');
    }
    curl_close($curl);
    $response = json_decode($raw, true);
    if (!is_array($response)) {
        throw new RuntimeException('PayPal returned an invalid response');
    }
    return [$status, $response];
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    paypal_fail(405, 'Method not allowed');
}
if (!verify_bearer_token()) {
    paypal_fail(401, 'Unauthorized');
}
$userId = (int)($_SESSION['user_id'] ?? 0);
$gymId = (int)($_SESSION['gym_id'] ?? 0);
if ($userId <= 0 || $gymId <= 0 || !is_gym_billing_owner(getPDO(), $userId, $gymId)) {
    paypal_fail(403, 'Only the billing owner can manage this gym subscription');
}

$clientId = getenv('PAYPAL_CLIENT_ID') ?: '';
$secret = getenv('PAYPAL_CLIENT_SECRET') ?: '';
if ($clientId === '' || $secret === '') {
    paypal_fail(503, 'PayPal is not configured');
}
$base = (getenv('PAYPAL_MODE') === 'live')
    ? 'https://api-m.paypal.com'
    : 'https://api-m.sandbox.paypal.com';

try {
    $plans = subscription_plans();
    $currency = $plans['_currency'];
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        paypal_fail(400, 'Invalid request');
    }

    [$tokenStatus, $tokenResponse] = paypal_request(
        'POST',
        "$base/v1/oauth2/token",
        [
            'Authorization: Basic ' . base64_encode("$clientId:$secret"),
            'Content-Type: application/x-www-form-urlencoded',
        ],
        'grant_type=client_credentials'
    );
    if ($tokenStatus !== 200 || empty($tokenResponse['access_token'])) {
        error_log('PayPal authentication failed with HTTP ' . $tokenStatus);
        paypal_fail(502, 'PayPal authentication failed');
    }
    $headers = [
        'Authorization: Bearer ' . $tokenResponse['access_token'],
        'Content-Type: application/json',
    ];

    if (($input['action'] ?? '') === 'create') {
        if (gym_has_current_paid_subscription(getPDO(), $gymId)) {
            paypal_fail(409, 'This gym already has an active subscription');
        }
        $plan = (string)($input['plan'] ?? '');
        $plan = str_starts_with($plan, 'businessregistry_')
            ? substr($plan, strlen('businessregistry_'))
            : $plan;
        if (!isset($plans[$plan])) {
            paypal_fail(400, 'Invalid plan');
        }
        $paypalPlanId = getenv('PAYPAL_PLAN_' . strtoupper($plan) . '_ID') ?: '';
        if ($paypalPlanId === '') {
            paypal_fail(503, 'PayPal recurring plan is not configured');
        }
        [$planStatus, $providerPlan] = paypal_request(
            'GET',
            "$base/v1/billing/plans/" . rawurlencode($paypalPlanId),
            $headers
        );
        $regularCycle = null;
        foreach ($providerPlan['billing_cycles'] ?? [] as $cycle) {
            if (($cycle['tenure_type'] ?? '') === 'REGULAR') {
                $regularCycle = $cycle;
                break;
            }
        }
        if ($planStatus >= 300
            || ($providerPlan['status'] ?? '') !== 'ACTIVE'
            || !$regularCycle
            || ($regularCycle['frequency']['interval_unit'] ?? '') !== strtoupper($plans[$plan]['interval'])
            || (int)($regularCycle['frequency']['interval_count'] ?? 0) !== 1
            || (int)($regularCycle['total_cycles'] ?? -1) !== 0
            || ($regularCycle['pricing_scheme']['fixed_price']['value'] ?? '') !== $plans[$plan]['price']
            || ($regularCycle['pricing_scheme']['fixed_price']['currency_code'] ?? '') !== $currency) {
            error_log('Configured PayPal plan does not match the recurring plan price/currency/interval');
            paypal_fail(503, 'PayPal recurring plan does not match the configured price');
        }
        $frontendUrl = rtrim(getenv('APP_FRONTEND_URL') ?: 'http://localhost:4200', '/');
        if (!filter_var($frontendUrl, FILTER_VALIDATE_URL)
            || !in_array(parse_url($frontendUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            paypal_fail(503, 'Checkout return URL is not configured');
        }
        $returnUrl = $frontendUrl . '/subscribe';
        $subscriptionRequest = [
            'plan_id' => $paypalPlanId,
            'custom_id' => "$gymId|$userId|$plan",
            'application_context' => [
                'brand_name' => 'BusinessRegistry',
                'user_action' => 'SUBSCRIBE_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => $frontendUrl . '/paywall?cancelled=1',
            ],
        ];
        [$status, $response] = paypal_request(
            'POST',
            "$base/v1/billing/subscriptions",
            array_merge($headers, ['Prefer: return=representation']),
            json_encode($subscriptionRequest, JSON_THROW_ON_ERROR)
        );
        if ($status >= 300 || empty($response['id'])) {
            error_log('PayPal subscription creation failed with HTTP ' . $status);
            paypal_fail(502, 'PayPal subscription creation failed');
        }
        foreach ($response['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve' && !empty($link['href'])) {
                echo json_encode(['success' => true, 'subscription_id' => $response['id'], 'approve_url' => $link['href']]);
                exit;
            }
        }
        paypal_fail(502, 'PayPal subscription approval link missing');
    }

    if (($input['action'] ?? '') === 'confirm') {
        $subscriptionId = (string)($input['subscription_id'] ?? '');
        if (!preg_match('/^[A-Za-z0-9-]{8,64}$/', $subscriptionId)) {
            paypal_fail(400, 'Invalid subscription');
        }

        [$subscriptionStatus, $subscription] = paypal_request(
            'GET',
            "$base/v1/billing/subscriptions/$subscriptionId",
            $headers
        );
        if ($subscriptionStatus >= 300 || ($subscription['status'] ?? '') !== 'ACTIVE') {
            paypal_fail(402, 'PayPal subscription is not active');
        }
        [$ownerGymId, $ownerId, $plan] = array_pad(
            explode('|', (string)($subscription['custom_id'] ?? ''), 3),
            3,
            ''
        );
        $paypalPlanId = getenv('PAYPAL_PLAN_' . strtoupper($plan) . '_ID') ?: '';
        $lastPayment = $subscription['billing_info']['last_payment'] ?? [];
        if ((int)$ownerGymId !== $gymId
            || (int)$ownerId !== $userId
            || !isset($plans[$plan])
            || $paypalPlanId === ''
            || ($subscription['plan_id'] ?? '') !== $paypalPlanId
            || ($lastPayment['amount']['value'] ?? '') !== $plans[$plan]['price']
            || ($lastPayment['amount']['currency_code'] ?? '') !== $currency) {
            paypal_fail(403, 'Subscription does not belong to this gym or configured plan');
        }

        $nextBillingTime = strtotime((string)($subscription['billing_info']['next_billing_time'] ?? ''));
        save_gym_recurring_subscription(
            getPDO(),
            $gymId,
            'paypal',
            $subscriptionId,
            $plan,
            'active',
            $nextBillingTime ? date('Y-m-d H:i:s', $nextBillingTime) : null,
            (string)($subscription['subscriber']['payer_id'] ?? ''),
            false
        );
        echo json_encode([
            'success' => true,
            'transaction_id' => $subscriptionId,
            'subscription' => compute_gym_subscription([
                'id' => $userId,
                'gym_id' => $gymId,
                'role' => $_SESSION['user_role'] ?? '',
            ], getPDO()),
        ]);
        exit;
    }

    paypal_fail(400, 'Unknown action');
} catch (InvalidArgumentException $e) {
    paypal_fail(400, 'Payment does not match a configured plan');
} catch (PDOException $e) {
    error_log('Could not save PayPal payment: ' . $e->getMessage());
    paypal_fail(500, 'Could not activate PayPal payment');
} catch (RuntimeException $e) {
    error_log('PayPal checkout error: ' . $e->getMessage());
    paypal_fail(502, 'PayPal is temporarily unavailable');
}
