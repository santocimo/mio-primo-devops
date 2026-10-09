<?php

require_once __DIR__ . '/subscription_plans.php';

function is_gym_billing_owner(PDO $pdo, int $userId, int $gymId): bool {
    if ($userId <= 0 || $gymId <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT billing_owner_user_id FROM gyms WHERE id = ? LIMIT 1');
    $stmt->execute([$gymId]);
    return (int)$stmt->fetchColumn() === $userId;
}

function gym_has_current_paid_subscription(PDO $pdo, int $gymId): bool {
    $stmt = $pdo->prepare(
        'SELECT provider_subscription_id, status, current_period_end
         FROM gym_subscriptions WHERE gym_id = ? LIMIT 1'
    );
    $stmt->execute([$gymId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$subscription) {
        return false;
    }
    if (in_array($subscription['status'], ['active', 'past_due'], true)
        && empty($subscription['current_period_end'])) {
        return true;
    }
    return in_array($subscription['status'], ['active', 'past_due', 'canceled'], true)
        && !empty($subscription['current_period_end'])
        && strtotime($subscription['current_period_end']) >= time();
}

function save_gym_recurring_subscription(
    PDO $pdo,
    int $gymId,
    string $provider,
    string $providerSubscriptionId,
    string $plan,
    string $status,
    ?string $periodEnd,
    ?string $providerCustomerId = null,
    bool $cancelAtPeriodEnd = false
): void {
    $plans = subscription_plans();
    $allowedStatuses = [
        'trialing',
        'active',
        'past_due',
        'canceled',
        'unpaid',
        'suspended',
        'expired',
        'incomplete',
        'incomplete_expired',
        'paused',
    ];
    if ($gymId <= 0
        || !in_array($provider, ['stripe', 'paypal'], true)
        || $providerSubscriptionId === ''
        || strlen($providerSubscriptionId) > 255
        || !isset($plans[$plan])
        || !in_array($status, $allowedStatuses, true)) {
        throw new InvalidArgumentException('Invalid recurring subscription details');
    }

    $check = $pdo->prepare(
        'SELECT gym_id FROM gym_subscriptions WHERE provider = ? AND provider_subscription_id = ? LIMIT 1'
    );
    $check->execute([$provider, $providerSubscriptionId]);
    $existingGymId = $check->fetchColumn();
    if ($existingGymId !== false && (int)$existingGymId !== $gymId) {
        throw new RuntimeException('Provider subscription is already linked to another gym');
    }

    $stmt = $pdo->prepare(
        'UPDATE gym_subscriptions
         SET status = ?, plan = ?, provider = ?, provider_subscription_id = ?,
             provider_customer_id = ?, current_period_end = ?, cancel_at_period_end = ?
         WHERE gym_id = ?'
    );
    $stmt->execute([
        $status,
        $plan,
        $provider,
        $providerSubscriptionId,
        $providerCustomerId,
        $periodEnd,
        $cancelAtPeriodEnd ? 1 : 0,
        $gymId,
    ]);
    if ($stmt->rowCount() === 0) {
        $exists = $pdo->prepare('SELECT gym_id FROM gym_subscriptions WHERE gym_id = ? LIMIT 1');
        $exists->execute([$gymId]);
        if (!$exists->fetchColumn()) {
            throw new RuntimeException('Gym subscription record not found');
        }
    }
}

function find_gym_subscription_by_provider_id(PDO $pdo, string $provider, string $subscriptionId): ?array {
    $stmt = $pdo->prepare(
        'SELECT gym_id, plan, status, provider_customer_id, current_period_end, cancel_at_period_end
         FROM gym_subscriptions WHERE provider = ? AND provider_subscription_id = ? LIMIT 1'
    );
    $stmt->execute([$provider, $subscriptionId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    return $subscription ?: null;
}

function record_gym_subscription_payment(
    PDO $pdo,
    string $provider,
    string $paymentId,
    int $gymId,
    string $plan,
    int $amountMinor,
    string $currency
): void {
    $plans = subscription_plans();
    if (!in_array($provider, ['stripe', 'paypal'], true)
        || $paymentId === ''
        || strlen($paymentId) > 255
        || $gymId <= 0
        || !isset($plans[$plan])
        || $amountMinor !== $plans[$plan]['amount_minor']
        || strtoupper($currency) !== $plans['_currency']) {
        throw new InvalidArgumentException('Recurring payment does not match a configured plan');
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO subscription_payments (provider, provider_payment_id, gym_id, plan, amount_minor, currency, paid_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$provider, $paymentId, $gymId, $plan, $amountMinor, strtoupper($currency)]);
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') {
            throw $e;
        }
        $existing = $pdo->prepare(
            'SELECT gym_id, plan, amount_minor, currency FROM subscription_payments
             WHERE provider = ? AND provider_payment_id = ? LIMIT 1'
        );
        $existing->execute([$provider, $paymentId]);
        $payment = $existing->fetch(PDO::FETCH_ASSOC);
        if (!$payment
            || (int)$payment['gym_id'] !== $gymId
            || $payment['plan'] !== $plan
            || (int)$payment['amount_minor'] !== $amountMinor
            || $payment['currency'] !== strtoupper($currency)) {
            throw new RuntimeException('Recurring payment idempotency conflict');
        }
    }
}
