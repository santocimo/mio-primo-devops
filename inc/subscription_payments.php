<?php

require_once __DIR__ . '/subscription.php';
require_once __DIR__ . '/subscription_plans.php';

function activate_subscription_payment(
    PDO $pdo,
    string $provider,
    string $paymentId,
    int $userId,
    string $planId,
    int $amountMinor,
    string $currency
): array {
    $plans = subscription_plans();
    $planKey = str_starts_with($planId, 'businessregistry_')
        ? substr($planId, strlen('businessregistry_'))
        : $planId;
    if (!in_array($provider, ['paypal', 'stripe'], true)
        || !isset($plans[$planKey])
        || $userId <= 0
        || $paymentId === ''
        || strlen($paymentId) > 255
        || $amountMinor !== $plans[$planKey]['amount_minor']
        || strtoupper($currency) !== $plans['_currency']) {
        throw new InvalidArgumentException('Paid subscription details do not match a configured plan');
    }

    try {
        $pdo->beginTransaction();
        $insert = $pdo->prepare(
            'INSERT INTO subscription_payments (provider, provider_payment_id, user_id, plan, amount_minor, currency, paid_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $insert->execute([$provider, $paymentId, $userId, $planKey, $amountMinor, strtoupper($currency)]);

        $select = $pdo->prepare('SELECT subscription_status, subscription_expires_at FROM users WHERE id = ? FOR UPDATE');
        $select->execute([$userId]);
        $user = $select->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            throw new RuntimeException('Subscription owner not found');
        }

        $start = time();
        if (($user['subscription_status'] ?? '') === 'active'
            && !empty($user['subscription_expires_at'])
            && strtotime($user['subscription_expires_at']) > $start) {
            $start = strtotime($user['subscription_expires_at']);
        }
        $expiry = (new DateTimeImmutable('@' . $start))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->modify('+' . $plans[$planKey]['duration_months'] . ' months')
            ->format('Y-m-d H:i:s');

        $update = $pdo->prepare(
            "UPDATE users SET subscription_status = 'active', subscription_plan = ?, subscription_expires_at = ? WHERE id = ?"
        );
        $update->execute([$planKey, $expiry, $userId]);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23000') {
            $check = $pdo->prepare('SELECT user_id, plan, amount_minor, currency FROM subscription_payments WHERE provider = ? AND provider_payment_id = ?');
            $check->execute([$provider, $paymentId]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);
            if ($existing
                && (int)$existing['user_id'] === $userId
                && $existing['plan'] === $planKey
                && (int)$existing['amount_minor'] === $amountMinor
                && $existing['currency'] === strtoupper($currency)) {
                return load_subscription_for_user($pdo, $userId);
            }
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return load_subscription_for_user($pdo, $userId);
}

function load_subscription_for_user(PDO $pdo, int $userId): array {
    $query = $pdo->prepare(
        'SELECT role, trial_start_date, subscription_status, subscription_plan, subscription_expires_at
         FROM users WHERE id = ? LIMIT 1'
    );
    $query->execute([$userId]);
    $user = $query->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new RuntimeException('Subscription owner not found');
    }
    return compute_subscription($user);
}
