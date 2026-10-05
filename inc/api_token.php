<?php

function api_token_secret(): string {
    $secret = getenv('APP_TOKEN_SECRET') ?: '';
    if (strlen($secret) < 32) {
        throw new RuntimeException('APP_TOKEN_SECRET must contain at least 32 characters');
    }
    return $secret;
}

function api_token_base64url_encode(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function api_token_base64url_decode(string $value): ?string {
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
        return null;
    }
    $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    return $decoded === false ? null : $decoded;
}

function create_api_token(array $user): string {
    $now = time();
    $payload = [
        'user_id' => (int)$user['id'],
        'username' => (string)$user['username'],
        'role' => strtoupper((string)$user['role']),
        'gym_id' => isset($user['gym_id']) ? (int)$user['gym_id'] : null,
        'iat' => $now,
        'exp' => $now + 86400,
    ];
    $encodedPayload = api_token_base64url_encode(json_encode($payload, JSON_THROW_ON_ERROR));
    $signature = hash_hmac('sha256', $encodedPayload, api_token_secret(), true);
    return $encodedPayload . '.' . api_token_base64url_encode($signature);
}

function decode_api_token(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return null;
    }

    [$encodedPayload, $encodedSignature] = $parts;
    $signature = api_token_base64url_decode($encodedSignature);
    $payloadJson = api_token_base64url_decode($encodedPayload);
    if ($signature === null || $payloadJson === null) {
        return null;
    }

    $expected = hash_hmac('sha256', $encodedPayload, api_token_secret(), true);
    if (!hash_equals($expected, $signature)) {
        return null;
    }

    $payload = json_decode($payloadJson, true);
    if (!is_array($payload)
        || !isset($payload['user_id'], $payload['username'], $payload['role'], $payload['iat'], $payload['exp'])
        || !is_int($payload['user_id'])
        || !is_string($payload['username'])
        || !is_string($payload['role'])
        || !is_int($payload['iat'])
        || !is_int($payload['exp'])
        || $payload['iat'] > time() + 60
        || $payload['exp'] <= time()
        || $payload['exp'] <= $payload['iat']
        || $payload['exp'] - $payload['iat'] > 86400) {
        return null;
    }

    return $payload;
}
