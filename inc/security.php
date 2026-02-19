<?php
// Central security bootstrap: secure session, headers, CSRF token
if (session_status() === PHP_SESSION_NONE) {
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Basic HTTP hardening headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer-when-downgrade");
// Simple CSP. Adjust as needed for external assets.
header("Content-Security-Policy: default-src 'self' https: 'unsafe-inline' 'unsafe-eval'; img-src 'self' data: https:; connect-src 'self' https:; frame-ancestors 'none';");

// Ensure a CSRF token is available
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

function verify_csrf($token) {
    if (empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

?>
