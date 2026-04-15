<?php
/**
 * activate_subscription.php
 * Attiva l'abbonamento sul DB e aggiorna la sessione.
 * In produzione: chiamare solo dopo conferma pagamento dal gateway.
 */
require_once __DIR__ . '/inc/security.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/inc/subscription.php';

if (!isset($_SESSION['admin_logged'])) { header('Location: login.php'); exit; }
if (empty($_POST['csrf']) || !verify_csrf($_POST['csrf'])) { header('Location: paywall.php'); exit; }

$plan = $_POST['plan'] ?? '';
if (!in_array($plan, ['monthly', 'yearly'], true)) { header('Location: paywall.php'); exit; }

$expires = ($plan === 'yearly')
    ? date('Y-m-d H:i:s', strtotime('+1 year'))
    : date('Y-m-d H:i:s', strtotime('+1 month'));

try {
    $pdo = getPDO();

    // Trova utente dalla sessione
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id && !empty($_SESSION['username'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$_SESSION['username']]);
        $row = $stmt->fetch();
        $user_id = $row ? (int)$row['id'] : null;
    }
    if (!$user_id && !empty($_SESSION['gym_id'])) {
        // Ultimo fallback: gym_id univoco (una sola palestra = un solo operatore principale)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE gym_id = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([(int)$_SESSION['gym_id']]);
        $row = $stmt->fetch();
        $user_id = $row ? (int)$row['id'] : null;
    }

    if (!$user_id) {
        header('Location: paywall.php?error=noid');
        exit;
    }

    $pdo->prepare("UPDATE users SET subscription_status = 'active', subscription_plan = ?, subscription_expires_at = ? WHERE id = ?")
        ->execute([$plan, $expires, $user_id]);

    // Aggiorna sessione
    $_SESSION['user_id']                 = $user_id;
    $_SESSION['subscription_status']    = 'active';
    $_SESSION['subscription_plan']      = $plan;
    $_SESSION['subscription_expires_at'] = $expires;

    header('Location: index.php');
    exit;
} catch (Exception $e) {
    header('Location: paywall.php?error=' . urlencode($e->getMessage()));
    exit;
}
