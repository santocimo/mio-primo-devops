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
        return ['status' => 'active', 'trial_start_date' => null, 'trial_ends_at' => null, 'trial_days_remaining' => 0, 'expires_at' => null, 'plan' => null];
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
    ];
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
