<?php

// Stripe >= 2025-03-31 espone la scadenza del periodo sugli item, non sulla sottoscrizione.
function stripe_subscription_period_end_timestamp(array $subscription): int {
    $end = (int)($subscription['current_period_end'] ?? 0);
    if ($end > 0) {
        return $end;
    }
    $ends = [];
    foreach ($subscription['items']['data'] ?? [] as $item) {
        $itemEnd = (int)($item['current_period_end'] ?? 0);
        if ($itemEnd > 0) {
            $ends[] = $itemEnd;
        }
    }
    return $ends ? max($ends) : 0;
}

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
