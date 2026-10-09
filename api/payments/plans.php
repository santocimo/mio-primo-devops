<?php
require_once __DIR__ . '/../../inc/security.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth/verify_token.php';
require_once __DIR__ . '/../../inc/subscription_plans.php';
require_once __DIR__ . '/../../inc/recurring_subscriptions.php';
require_once __DIR__ . '/../../inc/subscription.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}
if (!verify_bearer_token()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $plans = subscription_plans();
    $currency = $plans['_currency'];
    unset($plans['_currency']);
    $gymId = (int)($_SESSION['gym_id'] ?? 0);
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $gymSubscription = $gymId > 0
        ? compute_gym_subscription(['role' => $_SESSION['user_role'] ?? '', 'gym_id' => $gymId], getPDO())
        : null;
    echo json_encode([
        'success' => true,
        'currency' => $currency,
        'providers' => [
            'paypal' => (getenv('PAYPAL_CLIENT_ID') ?: '') !== ''
                && (getenv('PAYPAL_CLIENT_SECRET') ?: '') !== ''
                && (getenv('PAYPAL_WEBHOOK_ID') ?: '') !== ''
                && (getenv('PAYPAL_PLAN_MONTHLY_ID') ?: '') !== ''
                && (getenv('PAYPAL_PLAN_YEARLY_ID') ?: '') !== '',
            'stripe' => (getenv('STRIPE_SECRET_KEY') ?: '') !== '' && (getenv('STRIPE_WEBHOOK_SECRET') ?: '') !== '',
        ],
        'subscription' => $gymSubscription,
        'can_manage_subscription' => $gymId > 0 && is_gym_billing_owner(getPDO(), $userId, $gymId),
        'plans' => array_map(
            static fn(string $id, array $plan): array => [
                'id' => 'businessregistry_' . $id,
                'duration' => $id,
                'amount_minor' => $plan['amount_minor'],
                'currency' => $currency,
                'interval' => $plan['interval'],
            ],
            array_keys($plans),
            array_values($plans)
        ),
    ]);
} catch (RuntimeException $e) {
    error_log('Could not load subscription plans: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Plan configuration error']);
}
