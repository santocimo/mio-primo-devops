<?php

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../inc/security.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth/verify_token.php';
require_once __DIR__ . '/../src/Service/TerminologyService.php';

use App\Service\TerminologyService;

// Accetta sia sessione PHP (web) che Bearer token (app mobile)
if (!verify_bearer_token()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get category from query parameter or path
$category = trim((string)($_GET['category'] ?? ''));

// Also check if it's in the path: /api/terminology.php/gym
$pathParts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
if (empty($category) && count($pathParts) > 2) {
    $category = trim((string)end($pathParts));
}

if (empty($category)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Category parameter required']);
    exit;
}

$labels = TerminologyService::getContactLabel($category);
$labelKey = TerminologyService::getContactLabelKey($category);

echo json_encode([
    'success' => true,
    'category' => $category,
    'labels' => $labels,
    'label_key' => $labelKey,
    'is_member_category' => TerminologyService::isMemberCategory($category),
    'is_client_category' => TerminologyService::isClientCategory($category),
    'is_patient_category' => TerminologyService::isPatientCategory($category),
]);
exit;
