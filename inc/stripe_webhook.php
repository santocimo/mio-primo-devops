<?php

function verify_stripe_webhook_signature(
    string $payload,
    string $signatureHeader,
    string $secret,
    ?int $now = null
): bool {
    if ($secret === '') {
        return false;
    }

    $timestamp = null;
    $signatures = [];
    foreach (explode(',', $signatureHeader) as $part) {
        [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($key === 't' && ctype_digit($value)) {
            $timestamp = (int)$value;
        } elseif ($key === 'v1' && preg_match('/^[a-f0-9]{64}$/i', $value)) {
            $signatures[] = $value;
        }
    }

    $now ??= time();
    if ($timestamp === null || abs($now - $timestamp) > 300 || !$signatures) {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    $valid = false;
    foreach ($signatures as $signature) {
        $valid = hash_equals($expected, $signature) || $valid;
    }
    return $valid;
}
