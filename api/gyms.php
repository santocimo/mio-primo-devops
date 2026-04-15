<?php
// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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

// Accetta sia sessione PHP (web) che Bearer token (app mobile)
if (!isset($_SESSION['admin_logged'])) {
    if (!verify_bearer_token()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

$pdo = getPDO();
$method = $_SERVER['REQUEST_METHOD'];
$role = strtoupper($_SESSION['user_role'] ?? '');
$isAdmin = strpos($role, 'ADMIN') !== false || strpos($role, 'SUPER') !== false;

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT id, name, slug, category, created_at FROM gyms ORDER BY name LIMIT 200");
    $stmt->execute();
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    exit;
}

if (!$isAdmin) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }

if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true);
    $name     = trim($d['name'] ?? '');
    $slug     = trim($d['slug'] ?? strtolower(preg_replace('/[^a-z0-9]+/','-',$name)));
    $category = trim($d['category'] ?? 'gym');
    if (!$name) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Name obbligatorio']); exit; }
    $stmt = $pdo->prepare("INSERT INTO gyms (name,slug,category) VALUES (?,?,?)");
    $stmt->execute([$name,$slug,$category]);
    echo json_encode(['success'=>true,'id'=>(int)$pdo->lastInsertId()]);
    exit;
}

if ($method === 'PUT') {
    $parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/'));
    $id = (int)end($parts);
    if (!$id) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'ID mancante']); exit; }
    $d = json_decode(file_get_contents('php://input'), true);
    $name     = trim($d['name'] ?? '');
    $slug     = trim($d['slug'] ?? '');
    $category = trim($d['category'] ?? 'gym');
    $stmt = $pdo->prepare("UPDATE gyms SET name=?,slug=?,category=? WHERE id=?");
    $stmt->execute([$name,$slug,$category,$id]);
    echo json_encode(['success'=>true]);
    exit;
}

if ($method === 'DELETE') {
    $parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/'));
    $id = (int)end($parts);
    if (!$id) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'ID mancante']); exit; }
    $pdo->prepare("DELETE FROM gyms WHERE id=?")->execute([$id]);
    echo json_encode(['success'=>true]);
    exit;
}

http_response_code(405);
echo json_encode(['success'=>false,'message'=>'Method not allowed']);
?>
