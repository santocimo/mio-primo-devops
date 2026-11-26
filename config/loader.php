<?php
/**
 * Configuration Loader
 * 
 * Loads environment variables from .env file if it exists.
 */

function load_environment_secret_files(array $secretNames): void
{
    foreach ($secretNames as $secretName) {
        $secretFile = getenv($secretName . '_FILE');
        if ($secretFile === false || $secretFile === '' || getenv($secretName) !== false) {
            continue;
        }

        if (!is_file($secretFile) || !is_readable($secretFile)) {
            throw new RuntimeException('Unable to read configured secret file for ' . $secretName);
        }

        $secretValue = file_get_contents($secretFile);
        if ($secretValue === false) {
            throw new RuntimeException('Unable to read configured secret file for ' . $secretName);
        }

        putenv($secretName . '=' . rtrim($secretValue, "\r\n"));
    }
}

load_environment_secret_files([
    'ADMIN_INITIAL_PASSWORD',
    'APP_TOKEN_SECRET',
    'DB_PASSWORD',
    'MAIL_PASSWORD',
    'PAYPAL_CLIENT_SECRET',
    'STRIPE_SECRET_KEY',
    'STRIPE_WEBHOOK_SECRET',
]);

// Load .env file if it exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }

        if (strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Remove quotes if present
        if ($value !== '' && $value[0] === '"') {
            $value = substr($value, 1, -1);
        }

        if (!isset($_SERVER[$key]) && !getenv($key)) {
            putenv("$key=$value");
        }
    }
}
