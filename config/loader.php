<?php
/**
 * Configuration Loader
 * 
 * Loads environment variables from .env file if it exists,
 * and provides a centralized config access point.
 */

class Config {
    private static $instance = null;
    private static $config = [];

    public static function load() {
        if (self::$instance !== null) {
            return self::$instance;
        }

        // Load .env file if it exists
        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile)) {
            self::loadEnvFile($envFile);
        }

        // Load configuration
        self::$config = require dirname(__DIR__) . '/config/app.php';
        self::$instance = new self();

        return self::$instance;
    }

    public static function get($key, $default = null) {
        if (self::$instance === null) {
            self::load();
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }

        return $value;
    }

    private static function loadEnvFile($path) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
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
}

// Initialize on include
Config::load();
