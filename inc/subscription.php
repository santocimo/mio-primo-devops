<?php
/**
 * Subscription / Trial checker
 * Include dopo inc/security.php nelle pagine protette.
 * Se il trial è scaduto e non c'è abbonamento attivo, redirect a paywall.php.
 */

const TRIAL_DAYS = 7;

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
