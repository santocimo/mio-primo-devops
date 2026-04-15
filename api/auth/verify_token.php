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
    $_SESSION['admin_logged'] = true;
    $_SESSION['user_id']      = $payload['user_id'];
    $_SESSION['username']     = $payload['username'];
    $_SESSION['user_role']    = $payload['role'] ?? 'USER';
    if (isset($payload['gym_id'])) {
        $_SESSION['gym_id'] = (int)$payload['gym_id'];
    }

    return true;
}
