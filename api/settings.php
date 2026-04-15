<?php
require_once __DIR__ . '/../inc/security.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../api/auth/verify_token.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// Auth via bearer token
if (!isset($_SESSION['admin_logged'])) {
    if (!verify_bearer_token()) {
        http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit;
    }
}

// Admin only
$role = strtoupper($_SESSION['user_role'] ?? '');
if (strpos($role, 'ADMIN') === false && strpos($role, 'SUPER') === false) {
    http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
}

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $val = getAppSetting($pdo, 'default_business_type', 'gym');
    echo json_encode(['default_business_type' => $val]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $allowed = ['gym', 'salon', 'studio', 'other'];
    $btype = $data['default_business_type'] ?? '';
    if (!in_array($btype, $allowed, true)) {
        http_response_code(400); echo json_encode(['error' => 'Invalid value']); exit;
    }
    if (setAppSetting($pdo, 'default_business_type', $btype)) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500); echo json_encode(['error' => 'Save failed']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method Not Allowed']);
