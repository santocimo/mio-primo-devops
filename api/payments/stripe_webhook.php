<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../inc/subscription_plans.php';
require_once __DIR__ . '/../../inc/subscription_payments.php';
require_once __DIR__ . '/../../inc/stripe_webhook.php';

header('Content-Type: application/json');

function stripe_webhook_fail(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    stripe_webhook_fail(405, 'Method not allowed');
}

$secret = getenv('STRIPE_WEBHOOK_SECRET') ?: '';
if ($secret === '') {
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
$type = $event['type'];
if (!in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
    echo json_encode(['success' => true, 'received' => true]);
    exit;
}

$session = $event['data']['object'];
if (($session['mode'] ?? '') !== 'payment' || ($session['payment_status'] ?? '') !== 'paid') {
    echo json_encode(['success' => true, 'received' => true]);
    exit;
}

try {
    $plans = subscription_plans();
    $plan = (string)($session['metadata']['plan'] ?? '');
    $userId = (int)($session['metadata']['user_id'] ?? 0);
    $currency = strtoupper((string)($session['currency'] ?? ''));
    if (empty($session['id'])
        || (int)($session['client_reference_id'] ?? 0) !== $userId
        || !isset($plans[$plan])
        || (int)($session['amount_total'] ?? 0) !== $plans[$plan]['amount_minor']
        || $currency !== $plans['_currency']) {
        stripe_webhook_fail(400, 'Payment details could not be verified');
    }

    activate_subscription_payment(
        getPDO(),
        'stripe',
        (string)$session['id'],
        $userId,
        $plan,
        (int)$session['amount_total'],
        $currency
    );
    echo json_encode(['success' => true, 'received' => true]);
} catch (Throwable $e) {
    error_log('Stripe webhook processing failed: ' . $e->getMessage());
    stripe_webhook_fail(500, 'Webhook processing failed');
}
