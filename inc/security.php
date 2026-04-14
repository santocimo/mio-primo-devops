<?php
/**
 * Security Bootstrap
 * 
 * This file initializes security features including sessions and CSRF protection.
 * For new code, use App\Auth\SessionManager directly.
 * 
 * @deprecated Use SessionManager from bootstrap.php
 */

require_once dirname(dirname(__FILE__)) . '/bootstrap.php';

use App\Auth\SessionManager;

// Make legacy functions available
function verify_csrf($token) {
    return SessionManager::verifyCSRFToken($token);
}

// Ensure CSRF token is available
SessionManager::generateCSRFToken();

