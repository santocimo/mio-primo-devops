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
    if ($isAdmin && !isset($_GET['gym_id'])) {
        // Admin: tutti i servizi con nome gym
        $stmt = $pdo->query("SELECT s.*, g.name AS gym_name FROM services s JOIN gyms g ON g.id=s.gym_id ORDER BY g.name, s.name");
    } else {
        $gym_id = (int)($_GET['gym_id'] ?? $_SESSION['gym_id'] ?? 1);
        $stmt = $pdo->prepare("SELECT s.*, g.name AS gym_name FROM services s JOIN gyms g ON g.id=s.gym_id WHERE s.gym_id=? ORDER BY s.name");
        $stmt->execute([$gym_id]);
    }
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    exit;
}

if (!$isAdmin) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }

if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true);
    $name     = trim($d['name'] ?? '');
    $gym_id   = (int)($d['gym_id'] ?? 0);
    $category = trim($d['category'] ?? 'general');
    $slug     = trim($d['slug'] ?? strtolower(preg_replace('/[^a-z0-9]+/','-',$name)));
    $duration = (int)($d['duration_minutes'] ?? 60);
    $capacity = (int)($d['capacity'] ?? 1);
    $price    = isset($d['price']) && $d['price'] !== '' ? (float)$d['price'] : null;
    $description = trim($d['description'] ?? '');
    if (!$name || !$gym_id) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Name e gym_id obbligatori']); exit; }
    $stmt = $pdo->prepare("INSERT INTO services (name,slug,gym_id,category,duration_minutes,capacity,price,description) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$name,$slug,$gym_id,$category,$duration,$capacity,$price,$description]);
    echo json_encode(['success'=>true,'id'=>(int)$pdo->lastInsertId()]);
    exit;
}

if ($method === 'PUT') {
    $parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/'));
    $id = (int)end($parts);
    if (!$id) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'ID mancante']); exit; }
    $d = json_decode(file_get_contents('php://input'), true);
    $name     = trim($d['name'] ?? '');
    $gym_id   = (int)($d['gym_id'] ?? 0);
    $category = trim($d['category'] ?? 'general');
    $slug     = trim($d['slug'] ?? '');
    $duration = (int)($d['duration_minutes'] ?? 60);
    $capacity = (int)($d['capacity'] ?? 1);
    $price    = isset($d['price']) && $d['price'] !== '' ? (float)$d['price'] : null;
    $description = trim($d['description'] ?? '');
    $stmt = $pdo->prepare("UPDATE services SET name=?,slug=?,gym_id=?,category=?,duration_minutes=?,capacity=?,price=?,description=? WHERE id=?");
    $stmt->execute([$name,$slug,$gym_id,$category,$duration,$capacity,$price,$description,$id]);
    echo json_encode(['success'=>true]);
    exit;
}

if ($method === 'DELETE') {
    $parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/'));
    $id = (int)end($parts);
    if (!$id) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'ID mancante']); exit; }
    $pdo->prepare("DELETE FROM services WHERE id=?")->execute([$id]);
    echo json_encode(['success'=>true]);
    exit;
}

http_response_code(405);
echo json_encode(['success'=>false,'message'=>'Method not allowed']);
?>
