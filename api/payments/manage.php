<?php
require_once __DIR__ . '/../../inc/security.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth/verify_token.php';
require_once __DIR__ . '/../../inc/recurring_subscriptions.php';
require_once __DIR__ . '/../../inc/subscription.php';
require_once __DIR__ . '/../../inc/stripe_webhook.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

function subscription_manage_fail(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function subscription_manage_provider_request(string $method, string $url, array $headers, ?string $body): array {
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
        error_log('Subscription management request failed: ' . $error);
        throw new RuntimeException('Provider request failed');
    }
    curl_close($curl);
    $response = $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($response)) {
        throw new RuntimeException('Provider returned an invalid response');
    }
    return [$status, $response];
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    subscription_manage_fail(405, 'Method not allowed');
}
if (!verify_bearer_token()) {
    subscription_manage_fail(401, 'Unauthorized');
}
$request = json_decode(file_get_contents('php://input'), true);
if (!is_array($request) || ($request['action'] ?? '') !== 'cancel') {
    subscription_manage_fail(400, 'Unknown subscription management action');
}

$pdo = getPDO();
$userId = (int)($_SESSION['user_id'] ?? 0);
$gymId = (int)($_SESSION['gym_id'] ?? 0);
if (!is_gym_billing_owner($pdo, $userId, $gymId)) {
    subscription_manage_fail(403, 'Only the billing owner can manage this subscription');
}
$stmt = $pdo->prepare(
    'SELECT provider, provider_subscription_id, provider_customer_id, plan, status, current_period_end, cancel_at_period_end
     FROM gym_subscriptions WHERE gym_id = ? LIMIT 1'
);
$stmt->execute([$gymId]);
$current = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$current || !$current['provider_subscription_id'] || !$current['provider']) {
    subscription_manage_fail(409, 'There is no provider subscription to cancel');
}
if (!empty($current['cancel_at_period_end']) || $current['status'] === 'canceled') {
    subscription_manage_fail(409, 'Subscription cancellation is already scheduled');
}

try {
    if ($current['provider'] === 'stripe') {
        $secret = getenv('STRIPE_SECRET_KEY') ?: '';
        if ($secret === '') {
            subscription_manage_fail(503, 'Stripe is not configured');
        }
        [$status, $response] = subscription_manage_provider_request(
            'POST',
            'https://api.stripe.com/v1/subscriptions/' . rawurlencode($current['provider_subscription_id']),
            ['Authorization: Bearer ' . $secret, 'Content-Type: application/x-www-form-urlencoded'],
            http_build_query(['cancel_at_period_end' => 'true'])
        );
        if ($status >= 300 || empty($response['id']) || empty($response['cancel_at_period_end'])) {
            error_log('Stripe cancellation request failed with HTTP ' . $status);
            subscription_manage_fail(502, 'Could not schedule Stripe cancellation');
        }
        $statusValue = (string)($response['status'] ?? $current['status']);
        $periodEnd = stripe_subscription_period_end_timestamp($response);
        $cancelAtPeriodEnd = !empty($response['cancel_at_period_end']);
    } elseif ($current['provider'] === 'paypal') {
        $clientId = getenv('PAYPAL_CLIENT_ID') ?: '';
        $secret = getenv('PAYPAL_CLIENT_SECRET') ?: '';
        if ($clientId === '' || $secret === '') {
            subscription_manage_fail(503, 'PayPal is not configured');
        }
        $base = getenv('PAYPAL_MODE') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
        [$tokenStatus, $token] = subscription_manage_provider_request(
            'POST',
            "$base/v1/oauth2/token",
            [
                'Authorization: Basic ' . base64_encode("$clientId:$secret"),
                'Content-Type: application/x-www-form-urlencoded',
            ],
            'grant_type=client_credentials'
        );
        if ($tokenStatus !== 200 || empty($token['access_token'])) {
            subscription_manage_fail(502, 'PayPal authentication failed');
        }
        [$status] = subscription_manage_provider_request(
            'POST',
            "$base/v1/billing/subscriptions/" . rawurlencode($current['provider_subscription_id']) . '/cancel',
            [
                'Authorization: Bearer ' . $token['access_token'],
                'Content-Type: application/json',
            ],
            json_encode(['reason' => 'Cancellation requested by billing owner'], JSON_THROW_ON_ERROR)
        );
        if ($status >= 300) {
            error_log('PayPal cancellation request failed with HTTP ' . $status);
            subscription_manage_fail(502, 'Could not cancel PayPal subscription');
        }
        $statusValue = 'canceled';
        $periodEnd = $current['current_period_end'] ? strtotime($current['current_period_end']) : 0;
        $cancelAtPeriodEnd = true;
    } else {
        subscription_manage_fail(409, 'Unknown subscription provider');
    }

    save_gym_recurring_subscription(
        $pdo,
        $gymId,
        (string)$current['provider'],
        (string)$current['provider_subscription_id'],
        (string)$current['plan'],
        $statusValue,
        $periodEnd > 0 ? date('Y-m-d H:i:s', $periodEnd) : null,
        (string)($current['provider_customer_id'] ?? ''),
        $cancelAtPeriodEnd
    );

    echo json_encode([
        'success' => true,
        'subscription' => compute_gym_subscription([
            'id' => $userId,
            'gym_id' => $gymId,
            'role' => $_SESSION['user_role'] ?? '',
        ], $pdo),
    ]);
} catch (Throwable $e) {
    error_log('Subscription cancellation failed: ' . $e->getMessage());
    subscription_manage_fail(502, 'Subscription cancellation failed');
}
