<?php
/**
 * Session Manager
 * 
 * Centralized session handling with security best practices
 */

namespace App\Auth;

class SessionManager {
    private static $initialized = false;

    public static function initialize() {
        if (self::$initialized) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            $config = include dirname(__DIR__) . '/../config/app.php';
            $sessionConfig = $config['session'];

            session_set_cookie_params([
                'lifetime' => $sessionConfig['lifetime'],
                'path' => '/',
                'domain' => '',
                'secure' => $sessionConfig['secure'],
                'httponly' => $sessionConfig['httponly'],
                'samesite' => $sessionConfig['samesite']
            ]);

            session_start();
        }

        self::$initialized = true;
    }

    public static function set($key, $value) {
        self::initialize();
        $_SESSION[$key] = $value;
    }

    public static function get($key, $default = null) {
        self::initialize();
        return $_SESSION[$key] ?? $default;
    }

    public static function has($key) {
        self::initialize();
        return isset($_SESSION[$key]);
    }

    public static function unset($key) {
        self::initialize();
        unset($_SESSION[$key]);
    }

    public static function destroy() {
        self::initialize();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    public static function generateCSRFToken() {
        self::initialize();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCSRFToken($token) {
        self::initialize();
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function isAuthenticated() {
        self::initialize();
        return !empty($_SESSION['admin_logged']);
    }

    public static function getUserRole() {
        self::initialize();
        return $_SESSION['user_role'] ?? 'GUEST';
    }

    public static function getGymId() {
        self::initialize();
        return $_SESSION['gym_id'] ?? null;
    }
}
