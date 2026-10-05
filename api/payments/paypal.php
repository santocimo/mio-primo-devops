<?php
require_once __DIR__ . '/../../inc/security.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth/verify_token.php';
require_once __DIR__ . '/../../inc/subscription_plans.php';
require_once __DIR__ . '/../../inc/subscription_payments.php';

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

function paypal_amount_to_minor(string $amount): ?int {
    if (!preg_match('/^\d{1,7}\.\d{2}$/', $amount)) {
        return null;
    }
    return (int)str_replace('.', '', $amount);
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
if ($userId <= 0) {
    paypal_fail(403, 'Only registered operators can subscribe');
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
        $plan = (string)($input['plan'] ?? '');
        $plan = str_starts_with($plan, 'businessregistry_')
            ? substr($plan, strlen('businessregistry_'))
            : $plan;
        if (!isset($plans[$plan])) {
            paypal_fail(400, 'Invalid plan');
        }
        $frontendUrl = rtrim(getenv('APP_FRONTEND_URL') ?: 'http://localhost:4200', '/');
        if (!filter_var($frontendUrl, FILTER_VALIDATE_URL)
            || !in_array(parse_url($frontendUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            paypal_fail(503, 'Checkout return URL is not configured');
        }
        $returnUrl = $frontendUrl . '/subscribe';
        $order = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'custom_id' => "$userId|$plan",
                'description' => $plans[$plan]['label'],
                'amount' => ['currency_code' => $currency, 'value' => $plans[$plan]['price']],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $frontendUrl . '/paywall?cancelled=1',
                'user_action' => 'PAY_NOW',
            ]]],
        ];
        [$status, $response] = paypal_request(
            'POST',
            "$base/v2/checkout/orders",
            $headers,
            json_encode($order, JSON_THROW_ON_ERROR)
        );
        if ($status >= 300 || empty($response['id'])) {
            error_log('PayPal order creation failed with HTTP ' . $status);
            paypal_fail(502, 'PayPal order creation failed');
        }
        foreach ($response['links'] ?? [] as $link) {
            if (in_array($link['rel'] ?? '', ['payer-action', 'approve'], true) && !empty($link['href'])) {
                echo json_encode(['success' => true, 'order_id' => $response['id'], 'approve_url' => $link['href']]);
                exit;
            }
        }
        paypal_fail(502, 'PayPal approval link missing');
    }

    if (($input['action'] ?? '') === 'capture') {
        $orderId = (string)($input['order_id'] ?? '');
        if (!preg_match('/^[A-Za-z0-9]{8,32}$/', $orderId)) {
            paypal_fail(400, 'Invalid order');
        }

        [$orderStatus, $order] = paypal_request('GET', "$base/v2/checkout/orders/$orderId", $headers);
        if ($orderStatus >= 300 || !in_array($order['status'] ?? '', ['APPROVED', 'COMPLETED'], true)) {
            paypal_fail(402, 'PayPal order is not approved');
        }
        $purchaseUnit = $order['purchase_units'][0] ?? [];
        [$ownerId, $plan] = array_pad(explode('|', (string)($purchaseUnit['custom_id'] ?? ''), 2), 2, '');
        $amount = $purchaseUnit['amount'] ?? [];
        if ((int)$ownerId !== $userId
            || !isset($plans[$plan])
            || ($amount['value'] ?? '') !== $plans[$plan]['price']
            || ($amount['currency_code'] ?? '') !== $currency) {
            paypal_fail(403, 'Order does not belong to this user or configured plan');
        }

        if (($order['status'] ?? '') === 'COMPLETED') {
            $captureResponse = $order;
        } else {
            [$captureStatus, $captureResponse] = paypal_request(
                'POST',
                "$base/v2/checkout/orders/$orderId/capture",
                $headers,
                '{}'
            );
            if ($captureStatus >= 300 || ($captureResponse['status'] ?? '') !== 'COMPLETED') {
                paypal_fail(402, 'Payment capture failed');
            }
        }
        $capture = $captureResponse['purchase_units'][0]['payments']['captures'][0] ?? [];
        if (($capture['status'] ?? '') !== 'COMPLETED'
            || ($capture['amount']['currency_code'] ?? '') !== $currency
            || paypal_amount_to_minor((string)($capture['amount']['value'] ?? '')) !== $plans[$plan]['amount_minor']) {
            paypal_fail(402, 'Payment amount mismatch');
        }

        $subscription = activate_subscription_payment(
            getPDO(),
            'paypal',
            (string)($capture['id'] ?? $orderId),
            $userId,
            $plan,
            $plans[$plan]['amount_minor'],
            $currency
        );
        echo json_encode([
            'success' => true,
            'transaction_id' => $capture['id'] ?? $orderId,
            'subscription' => $subscription,
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
