<?php
// Simple router for PHP development server
// Handles URL rewriting like Apache mod_rewrite

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/api/', '', $uri);

// Remove leading slash
$uri = ltrim($uri, '/');

// Map API routes to actual files
$routes = [
    'auth/login' => 'api/auth/login.php',
    'gyms' => 'api/gyms.php',
    'services' => 'api/services.php',
    'appointments' => 'api/appointments.php',
    'contacts' => 'api/contacts.php',
    'users' => 'api/users.php',
    'settings' => 'api/settings.php',
];

foreach ($routes as $route => $file) {
    if (strpos($_SERVER['REQUEST_URI'], '/api/' . $route) === 0) {
        $_SERVER['SCRIPT_NAME'] = '/' . $file;
        $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/' . $file;
        require __DIR__ . '/' . $file;
        exit;
    }
}

// If not a route, serve the file if it exists
$file = __DIR__ . $_SERVER['REQUEST_URI'];
if (file_exists($file) && is_file($file)) {
    return false;
}

// Otherwise 404
http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Not found']);
?>
