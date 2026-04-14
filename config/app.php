<?php
/**
 * Application Configuration
 * 
 * This file loads environment variables and provides configuration
 * for the entire application.
 */

return [
    'name' => getenv('APP_NAME') ?: 'BusinessRegistry',
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => getenv('APP_DEBUG') === 'true',
    'url' => getenv('APP_URL') ?: 'http://localhost',

    'database' => [
        'driver' => 'mysql',
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => (int)(getenv('DB_PORT') ?: 3306),
        'database' => getenv('DB_NAME') ?: 'app_database',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    'logging' => [
        'level' => getenv('LOG_LEVEL') ?: 'error',
        'channel' => getenv('LOG_CHANNEL') ?: 'file',
        'path' => dirname(__DIR__) . '/logs',
    ],

    'session' => [
        'lifetime' => (int)(getenv('SESSION_LIFETIME') ?: 3600),
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ],

    'security' => [
        'csrf_protection' => getenv('CSRF_PROTECTION') !== 'false',
    ],
];
