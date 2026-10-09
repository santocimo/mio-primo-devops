<?php
/**
 * Subscription / Trial checker
 * Include dopo inc/security.php nelle pagine protette.
 * Se il trial è scaduto e non c'è abbonamento attivo, redirect a paywall.php.
 */

const TRIAL_DAYS = 7;

/**
 * Calcola lo stato abbonamento di un record utente (fonte di verità: DB).
 * Admin/super sempre attivi; operatori in prova per TRIAL_DAYS dalla registrazione.
 */
function compute_subscription(array $user): array {
    $role = strtoupper($user['role'] ?? '');
    if (str_contains($role, 'ADMIN') || str_contains($role, 'SUPER')) {
        return [
            'status' => 'active',
            'trial_start_date' => null,
            'trial_ends_at' => null,
            'trial_days_remaining' => 0,
            'expires_at' => null,
            'plan' => null,
            'auto_renew' => false,
            'cancel_at_period_end' => false,
        ];
    }

    $start = $user['trial_start_date'] ?? null;
    $expires = $user['subscription_expires_at'] ?? null;
    $status = $user['subscription_status'] ?? 'none';
    $trialEnds = $start ? date('Y-m-d H:i:s', strtotime($start) + TRIAL_DAYS * 86400) : null;
    $daysLeft = $trialEnds ? max(0, (int)ceil((strtotime($trialEnds) - time()) / 86400)) : 0;

    if ($status === 'active' && (!$expires || strtotime($expires) >= time())) {
        $effective = 'active';
    } elseif ($status === 'trial' && $daysLeft > 0) {
        $effective = 'trial';
    } else {
        $effective = 'expired';
    }

    return [
        'status' => $effective,
        'trial_start_date' => $start ? date('c', strtotime($start)) : null,
        'trial_ends_at' => $trialEnds ? date('c', strtotime($trialEnds)) : null,
        'trial_days_remaining' => $daysLeft,
        'expires_at' => $expires ? date('c', strtotime($expires)) : null,
        'plan' => $user['subscription_plan'] ?? null,
        'auto_renew' => false,
        'cancel_at_period_end' => false,
    ];
}

function compute_gym_subscription(array $user, PDO $pdo): array {
    $role = strtoupper((string)($user['role'] ?? ''));
    if (str_contains($role, 'ADMIN') || str_contains($role, 'SUPER')) {
        return compute_subscription($user);
    }

    $gymId = (int)($user['gym_id'] ?? 0);
    if ($gymId <= 0) {
        return compute_subscription($user);
    }
    $stmt = $pdo->prepare(
        'SELECT trial_start_date, status, plan, current_period_end, provider, cancel_at_period_end
         FROM gym_subscriptions WHERE gym_id = ? LIMIT 1'
    );
    $stmt->execute([$gymId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$subscription) {
        return compute_subscription($user);
    }

    return compute_gym_subscription_state($user, $subscription);
}

function compute_gym_subscription_state(array $user, array $subscription): array {
    $effectiveStatus = (string)$subscription['status'];
    if (in_array($effectiveStatus, ['canceled', 'past_due'], true)
        && !empty($subscription['current_period_end'])
        && strtotime($subscription['current_period_end']) >= time()) {
        $effectiveStatus = 'active';
    }
    $effective = compute_subscription([
        'role' => $user['role'] ?? '',
        'trial_start_date' => $subscription['trial_start_date'],
        'subscription_status' => $effectiveStatus,
        'subscription_plan' => $subscription['plan'],
        'subscription_expires_at' => $subscription['current_period_end'],
    ]);
    $effective['auto_renew'] = !empty($subscription['provider']) && empty($subscription['cancel_at_period_end']);
    $effective['cancel_at_period_end'] = (bool)$subscription['cancel_at_period_end'];
    return $effective;
}

function get_subscription_status(): string {
    // Admin/Super non soggetti a trial
    $role = strtoupper($_SESSION['user_role'] ?? '');
    if (str_contains($role, 'ADMIN') || str_contains($role, 'SUPER')) {
        return 'active';
    }

    $status = $_SESSION['subscription_status'] ?? 'none';

    // Abbonamento esplicito attivo
    if ($status === 'active') {
        $expires = $_SESSION['subscription_expires_at'] ?? null;
        if ($expires && strtotime($expires) < time()) {
            return 'expired';
        }
        return 'active';
    }

    // Trial
    if ($status === 'trial') {
        $start = $_SESSION['trial_start_date'] ?? null;
        if (!$start) return 'expired';
        $elapsed = (time() - strtotime($start)) / 86400;
        return ($elapsed < TRIAL_DAYS) ? 'trial' : 'expired';
    }

    return 'expired';
}

function get_trial_days_remaining(): int {
    $start = $_SESSION['trial_start_date'] ?? null;
    if (!$start) return 0;
    $elapsed = (time() - strtotime($start)) / 86400;
    return max(0, (int)ceil(TRIAL_DAYS - $elapsed));
}

function require_subscription(): void {
    $status = get_subscription_status();
    if ($status === 'expired' || $status === 'none') {
        header('Location: paywall.php');
        exit;
    }
}
