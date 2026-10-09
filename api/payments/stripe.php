<?php
require_once __DIR__ . '/../../inc/security.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth/verify_token.php';
require_once __DIR__ . '/../../inc/subscription_plans.php';
require_once __DIR__ . '/../../inc/recurring_subscriptions.php';
require_once __DIR__ . '/../../inc/stripe_webhook.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

function stripe_fail(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function stripe_api_request(string $method, string $path, string $secret, ?array $form = null): array {
    $curl = curl_init('https://api.stripe.com' . $path);
    $headers = ['Authorization: Bearer ' . $secret];
    if ($form !== null) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($form !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($form));
    }
    $raw = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if (!is_string($raw)) {
        $error = curl_error($curl);
        curl_close($curl);
        error_log('Stripe request failed: ' . $error);
        throw new RuntimeException('Stripe request failed');
    }
    curl_close($curl);
    $response = json_decode($raw, true);
    if (!is_array($response)) {
        throw new RuntimeException('Stripe returned an invalid response');
    }
    return [$status, $response];
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    stripe_fail(405, 'Method not allowed');
}
if (!verify_bearer_token()) {
    stripe_fail(401, 'Unauthorized');
}
$userId = (int)($_SESSION['user_id'] ?? 0);
$gymId = (int)($_SESSION['gym_id'] ?? 0);
if ($userId <= 0 || $gymId <= 0 || !is_gym_billing_owner(getPDO(), $userId, $gymId)) {
    stripe_fail(403, 'Only the billing owner can manage this gym subscription');
}

$secret = getenv('STRIPE_SECRET_KEY') ?: '';
$webhookSecret = getenv('STRIPE_WEBHOOK_SECRET') ?: '';
if ($secret === '' || $webhookSecret === '') {
    stripe_fail(503, 'Card payment is not configured');
}

$request = json_decode(file_get_contents('php://input'), true);
if (!is_array($request)) {
    stripe_fail(400, 'Invalid request');
}
$action = $request['action'] ?? '';

try {
    $plans = subscription_plans();
    $currency = $plans['_currency'];

    if ($action === 'create') {
        if (gym_has_current_paid_subscription(getPDO(), $gymId)) {
            stripe_fail(409, 'This gym already has an active subscription');
        }
        $planId = (string)($request['plan'] ?? '');
        $planKey = str_starts_with($planId, 'businessregistry_')
            ? substr($planId, strlen('businessregistry_'))
            : $planId;
        if (!isset($plans[$planKey])) {
            stripe_fail(400, 'Invalid plan');
        }
        $frontendUrl = rtrim(getenv('APP_FRONTEND_URL') ?: 'http://localhost:4200', '/');
        $frontend = filter_var($frontendUrl, FILTER_VALIDATE_URL);
        if (!$frontend || !in_array(parse_url($frontendUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            stripe_fail(503, 'Checkout return URL is not configured');
        }
        // L'app nativa torna a sé stessa tramite deep link invece che via web
        $returnBase = !empty($request['native']) ? 'businessregistry:/' : $frontendUrl;
        $form = [
            'mode' => 'subscription',
            'success_url' => $returnBase . '/subscribe?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $returnBase . '/paywall?cancelled=1',
            'client_reference_id' => (string)$userId,
            'metadata[user_id]' => (string)$userId,
            'metadata[gym_id]' => (string)$gymId,
            'metadata[plan]' => $planKey,
            'line_items[0][quantity]' => '1',
            'line_items[0][price_data][currency]' => strtolower($currency),
            'line_items[0][price_data][unit_amount]' => (string)$plans[$planKey]['amount_minor'],
            'line_items[0][price_data][product_data][name]' => $plans[$planKey]['label'],
            'line_items[0][price_data][recurring][interval]' => $plans[$planKey]['interval'],
            'subscription_data[metadata][user_id]' => (string)$userId,
            'subscription_data[metadata][gym_id]' => (string)$gymId,
            'subscription_data[metadata][plan]' => $planKey,
        ];
        [$status, $session] = stripe_api_request('POST', '/v1/checkout/sessions', $secret, $form);
        if ($status >= 300 || empty($session['id']) || empty($session['url'])) {
            error_log('Stripe Checkout Session creation failed with HTTP ' . $status);
            stripe_fail(502, 'Could not create card checkout');
        }
        echo json_encode(['success' => true, 'session_id' => $session['id'], 'checkout_url' => $session['url']]);
        exit;
    }

    if ($action === 'confirm') {
        $sessionId = (string)($request['session_id'] ?? '');
        if (!preg_match('/^cs_(test|live)_[A-Za-z0-9]+$/', $sessionId)) {
            stripe_fail(400, 'Invalid checkout session');
        }
        [$status, $session] = stripe_api_request('GET', '/v1/checkout/sessions/' . rawurlencode($sessionId) . '?expand[]=subscription', $secret);
        if ($status >= 300) {
            stripe_fail(402, 'Could not verify card payment');
        }
        $planKey = (string)($session['metadata']['plan'] ?? '');
        $ownerId = (int)($session['metadata']['user_id'] ?? 0);
        $subscriptionData = $session['subscription'] ?? null;
        $subscriptionId = is_array($subscriptionData)
            ? (string)($subscriptionData['id'] ?? '')
            : (string)$subscriptionData;
        if (($session['status'] ?? '') !== 'complete'
            || !in_array($session['payment_status'] ?? '', ['paid', 'no_payment_required'], true)
            || ($session['mode'] ?? '') !== 'subscription'
            || (int)($session['client_reference_id'] ?? 0) !== $userId
            || $ownerId !== $userId
            || (int)($session['metadata']['gym_id'] ?? 0) !== $gymId
            || !isset($plans[$planKey])
            || (int)($session['amount_total'] ?? 0) !== $plans[$planKey]['amount_minor']
            || strtoupper((string)($session['currency'] ?? '')) !== $currency
            || $subscriptionId === '') {
            stripe_fail(402, 'Payment details could not be verified');
        }
        if (!is_array($subscriptionData)) {
            [$subscriptionStatus, $subscriptionData] = stripe_api_request(
                'GET',
                '/v1/subscriptions/' . rawurlencode($subscriptionId),
                $secret
            );
            if ($subscriptionStatus >= 300) {
                stripe_fail(402, 'Could not verify recurring subscription');
            }
        }
        $item = $subscriptionData['items']['data'][0] ?? [];
        $price = $item['price'] ?? [];
        $periodEnd = stripe_subscription_period_end_timestamp($subscriptionData);
        if (!in_array($subscriptionData['status'] ?? '', ['active', 'trialing'], true)
            || (int)($subscriptionData['metadata']['user_id'] ?? 0) !== $userId
            || (int)($subscriptionData['metadata']['gym_id'] ?? 0) !== $gymId
            || (string)($subscriptionData['metadata']['plan'] ?? '') !== $planKey
            || (int)($price['unit_amount'] ?? 0) !== $plans[$planKey]['amount_minor']
            || strtoupper((string)($price['currency'] ?? '')) !== $currency
            || ($price['recurring']['interval'] ?? '') !== $plans[$planKey]['interval']
            || $periodEnd <= 0) {
            stripe_fail(402, 'Recurring subscription details could not be verified');
        }
        save_gym_recurring_subscription(
            getPDO(),
            $gymId,
            'stripe',
            $subscriptionId,
            $planKey,
            (string)$subscriptionData['status'],
            date('Y-m-d H:i:s', $periodEnd),
            (string)($subscriptionData['customer'] ?? ''),
            !empty($subscriptionData['cancel_at_period_end'])
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

    stripe_fail(400, 'Unknown action');
} catch (InvalidArgumentException $e) {
    stripe_fail(400, 'Payment does not match a configured plan');
} catch (PDOException $e) {
    error_log('Could not save Stripe payment: ' . $e->getMessage());
    stripe_fail(500, 'Could not activate card payment');
} catch (RuntimeException $e) {
    error_log('Stripe checkout error: ' . $e->getMessage());
    stripe_fail(502, 'Card checkout is temporarily unavailable');
}
