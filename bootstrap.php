<?php
/**
 * Application Bootstrap
 * 
 * Initialize all required components, configuration, logging, etc.
 * This should be included at the start of every request.
 */

// Define root path
define('ROOT_PATH', __DIR__);

// Set error handling based on environment
$debug = getenv('APP_DEBUG') === 'true';

if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Load configuration
require_once ROOT_PATH . '/config/loader.php';

// Load composer autoloader
require_once ROOT_PATH . '/vendor/autoload.php';

// Initialize session
use App\Auth\SessionManager;
use App\Logger\Logger;

SessionManager::initialize();

// Set security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer-when-downgrade");
header("Content-Security-Policy: default-src 'self' https: 'unsafe-inline'; img-src 'self' data: https:; connect-src 'self' https:;");

// Error/Exception handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $logger = Logger::getInstance();
    $logger->error("PHP Error: $errstr", [
        'file' => $errfile,
        'line' => $errline,
        'code' => $errno
    ]);
    
    if (!getenv('APP_DEBUG')) {
        http_response_code(500);
        die('An error occurred. Please try again later.');
    }
});

set_exception_handler(function($exception) {
    $logger = Logger::getInstance();
    $logger->error('Uncaught exception: ' . $exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ]);
    
    if (!getenv('APP_DEBUG')) {
        http_response_code(500);
        die('An error occurred. Please try again later.');
    } else {
        echo '<pre>' . $exception . '</pre>';
    }
});
