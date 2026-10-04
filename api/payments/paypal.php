<?php
require_once __DIR__ . '/../../inc/security.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth/verify_token.php';
require_once __DIR__ . '/../../inc/subscription.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

function fail(int $status, string $msg): void {
    http_response_code($status);
    echo json_encode(['error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'Method not allowed');
if (!verify_bearer_token()) fail(401, 'Unauthorized');

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) fail(403, 'Only registered operators can subscribe');

$clientId = getenv('PAYPAL_CLIENT_ID') ?: '';
$secret = getenv('PAYPAL_CLIENT_SECRET') ?: '';
if ($clientId === '' || $secret === '') fail(503, 'PayPal is not configured');
$base = (getenv('PAYPAL_MODE') === 'live') ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

// Prezzi definiti solo lato server: il client sceglie soltanto il piano.
$plans = [
    'monthly' => ['value' => getenv('PAYPAL_PRICE_MONTHLY') ?: '4.99',  'interval' => '+1 month', 'label' => 'SmartRegistry - Piano Mensile'],
    'yearly'  => ['value' => getenv('PAYPAL_PRICE_YEARLY') ?: '49.99', 'interval' => '+1 year',  'label' => 'SmartRegistry - Piano Annuale'],
];
$currency = getenv('PAYPAL_CURRENCY') ?: 'EUR';

function paypal_request(string $method, string $url, array $headers, ?string $body = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, is_string($raw) ? (json_decode($raw, true) ?: []) : []];
}

[$code, $tok] = paypal_request('POST', "$base/v1/oauth2/token",
    ['Authorization: Basic ' . base64_encode("$clientId:$secret"), 'Content-Type: application/x-www-form-urlencoded'],
    'grant_type=client_credentials');
if ($code !== 200 || empty($tok['access_token'])) fail(502, 'PayPal authentication failed');
$auth = ['Authorization: Bearer ' . $tok['access_token'], 'Content-Type: application/json'];

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $in['action'] ?? '';

if ($action === 'create') {
    $plan = $in['plan'] ?? '';
    if (!isset($plans[$plan])) fail(400, 'Invalid plan');
    $returnUrl = (string)($in['return_url'] ?? '');
    $cancelUrl = (string)($in['cancel_url'] ?? $returnUrl);
    if (!filter_var($returnUrl, FILTER_VALIDATE_URL) || !filter_var($cancelUrl, FILTER_VALIDATE_URL)) fail(400, 'Invalid return URL');

    $order = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'custom_id' => "$userId|$plan",
            'description' => $plans[$plan]['label'],
            'amount' => ['currency_code' => $currency, 'value' => $plans[$plan]['value']],
        ]],
        'payment_source' => ['paypal' => ['experience_context' => [
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'user_action' => 'PAY_NOW',
        ]]],
    ];
    [$code, $res] = paypal_request('POST', "$base/v2/checkout/orders", $auth, json_encode($order));
    if ($code >= 300 || empty($res['id'])) fail(502, 'PayPal order creation failed');

    $approve = '';
    foreach ($res['links'] ?? [] as $l) {
        if (in_array($l['rel'] ?? '', ['payer-action', 'approve'], true)) { $approve = $l['href']; break; }
    }
    if ($approve === '') fail(502, 'PayPal approval link missing');
    echo json_encode(['success' => true, 'order_id' => $res['id'], 'approve_url' => $approve]);
    exit;
}

if ($action === 'capture') {
    $orderId = (string)($in['order_id'] ?? '');
    if (!preg_match('/^[A-Za-z0-9]{8,32}$/', $orderId)) fail(400, 'Invalid order');

    [$code, $res] = paypal_request('POST', "$base/v2/checkout/orders/$orderId/capture", $auth, '{}');
    if ($code >= 300) fail(402, 'Payment capture failed');
    if (($res['status'] ?? '') !== 'COMPLETED') fail(402, 'Payment not completed');

    $unit = $res['purchase_units'][0] ?? [];
    $capture = $unit['payments']['captures'][0] ?? [];
    [$ownerId, $plan] = array_pad(explode('|', (string)($capture['custom_id'] ?? $unit['custom_id'] ?? ''), 2), 2, '');
    if ((int)$ownerId !== $userId || !isset($plans[$plan])) fail(403, 'Order does not belong to this user');
    if (($capture['status'] ?? '') !== 'COMPLETED'
        || (float)($capture['amount']['value'] ?? 0) !== (float)$plans[$plan]['value']
        || ($capture['amount']['currency_code'] ?? '') !== $currency) {
        fail(402, 'Payment amount mismatch');
    }

    $pdo = getPDO();
    $q = $pdo->prepare('SELECT subscription_status, subscription_expires_at FROM users WHERE id = ?');
    $q->execute([$userId]);
    $row = $q->fetch(PDO::FETCH_ASSOC) ?: [];
    // Un rinnovo anticipato si somma alla scadenza ancora valida
    $from = time();
    if (($row['subscription_status'] ?? '') === 'active' && !empty($row['subscription_expires_at']) && strtotime($row['subscription_expires_at']) > $from) {
        $from = strtotime($row['subscription_expires_at']);
    }
    $expires = date('Y-m-d H:i:s', strtotime($plans[$plan]['interval'], $from));

    $u = $pdo->prepare("UPDATE users SET subscription_status = 'active', subscription_plan = ?, subscription_expires_at = ? WHERE id = ?");
    $u->execute([$plan, $expires, $userId]);

    $q = $pdo->prepare('SELECT role, trial_start_date, subscription_status, subscription_plan, subscription_expires_at FROM users WHERE id = ?');
    $q->execute([$userId]);
    echo json_encode(['success' => true, 'transaction_id' => $capture['id'] ?? $orderId, 'subscription' => compute_subscription($q->fetch(PDO::FETCH_ASSOC))]);
    exit;
}

fail(400, 'Unknown action');
