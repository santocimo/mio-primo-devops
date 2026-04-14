<?php
/**
 * Configuration Loader
 * 
 * Loads environment variables from .env file if it exists.
 */

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
