<?php
/**
 * Verifica il token Bearer inviato dall'app mobile.
 * Se valido, imposta le variabili di sessione necessarie alle API.
 * Restituisce true se autenticato, false altrimenti.
 */
function verify_bearer_token(): bool {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
        return false;
    }

    $token = $matches[1];

    // Decodifica il token (base64 di JSON)
    $decoded = base64_decode($token, true);
    if ($decoded === false) {
        return false;
    }

    $payload = json_decode($decoded, true);
    if (!$payload || !isset($payload['user_id'], $payload['username'], $payload['timestamp'])) {
        return false;
    }

    // Scadenza token: 24 ore
    if (time() - $payload['timestamp'] > 86400) {
        return false;
    }

    // Imposta sessione compatibile con le API esistenti
    $role = strtoupper($payload['role'] ?? 'USER');
    $_SESSION['user_id']   = $payload['user_id'];
    $_SESSION['username']  = $payload['username'];
    $_SESSION['user_role'] = $role;
    $_SESSION['admin_logged'] = (strpos($role, 'ADMIN') !== false || strpos($role, 'SUPER') !== false);
    if (isset($payload['gym_id'])) {
        $_SESSION['gym_id'] = (int)$payload['gym_id'];
    }

    return true;
}

// If called directly via HTTP, return a JSON response usable dall'app mobile
$isDirectRequest = isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__;
if (php_sapi_name() !== 'cli' && $isDirectRequest) {
    header('Content-Type: application/json');
    session_start();
    $ok = verify_bearer_token();
    if ($ok) {
        $user = [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'role' => $_SESSION['user_role'] ?? null,
            'gym_id' => $_SESSION['gym_id'] ?? null,
        ];
        require_once __DIR__ . '/../../db.php';
        require_once __DIR__ . '/../../inc/subscription.php';
        $subRow = ['role' => $user['role']];
        if ((int)$user['id'] > 0) {
            try {
                $q = getPDO()->prepare("SELECT role, trial_start_date, subscription_status, subscription_plan, subscription_expires_at FROM users WHERE id = ?");
                $q->execute([(int)$user['id']]);
                $subRow = $q->fetch(PDO::FETCH_ASSOC) ?: $subRow;
            } catch (Exception $e) {
                // stato non disponibile: tratta come scaduto
            }
        } else {
            $subRow['role'] = 'ADMIN';
        }
        echo json_encode(['success' => true, 'user' => $user, 'subscription' => compute_subscription($subRow)]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
