<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../inc/security.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth/verify_token.php';

if (!isset($_SESSION['admin_logged'])) {
    if (!verify_bearer_token()) {
        http_response_code(401); echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
    }
}

// Solo ADMIN
$role = strtoupper($_SESSION['user_role'] ?? '');
if (strpos($role, 'ADMIN') === false && strpos($role, 'SUPER') === false) {
    http_response_code(403); echo json_encode(['success' => false, 'message' => 'Forbidden']); exit;
}

$pdo = getPDO();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT id, username, role, gym_id, created_at FROM users ORDER BY username");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true);
    $username = trim($d['username'] ?? '');
    $password = $d['password'] ?? '';
    $userRole = strtoupper(trim($d['role'] ?? 'OPERATORE'));
    $gymId    = !empty($d['gym_id']) ? (int)$d['gym_id'] : null;

    if (!$username || !$password) {
        http_response_code(400); echo json_encode(['success' => false, 'message' => 'Username e password obbligatori']); exit;
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, gym_id) VALUES (?,?,?,?)");
    $stmt->execute([$username, $hash, $userRole, $gymId]);
    echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
    exit;
}

if ($method === 'PUT') {
    $parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
    $id = (int)end($parts);
    if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'ID mancante']); exit; }

    $d = json_decode(file_get_contents('php://input'), true);
    $username = trim($d['username'] ?? '');
    $userRole = strtoupper(trim($d['role'] ?? 'OPERATORE'));
    $gymId    = !empty($d['gym_id']) ? (int)$d['gym_id'] : null;
    $password = $d['password'] ?? '';

    if ($password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET username=?, password_hash=?, role=?, gym_id=? WHERE id=?");
        $stmt->execute([$username, $hash, $userRole, $gymId, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET username=?, role=?, gym_id=? WHERE id=?");
        $stmt->execute([$username, $userRole, $gymId, $id]);
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($method === 'DELETE') {
    $parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
    $id = (int)end($parts);
    if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'ID mancante']); exit; }
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
