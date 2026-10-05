<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../inc/security.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth/verify_token.php';

if (!verify_bearer_token()) {
    http_response_code(401); echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}
require_active_api_subscription();

$role = strtoupper($_SESSION['user_role'] ?? '');
if (strpos($role, 'ADMIN') === false && strpos($role, 'SUPER') === false && strpos($role, 'OPERATORE') === false) {
    http_response_code(403); echo json_encode(['success' => false, 'message' => 'Forbidden']); exit;
}

$isAdmin = strpos($role, 'ADMIN') !== false || strpos($role, 'SUPER') !== false;
$sessionGymId = isset($_SESSION['gym_id']) ? (int)$_SESSION['gym_id'] : null;

function load_user_scope(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare('SELECT id, role, gym_id FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

$pdo = getPDO();
$method = $_SERVER['REQUEST_METHOD'];

function users_table_has_column(PDO $pdo, string $column): bool {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = ?");
        $stmt->execute([$column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

if ($method === 'GET') {
    $selectName = users_table_has_column($pdo, 'name') ? 'name' : 'NULL AS name';
    $selectEmail = users_table_has_column($pdo, 'email') ? 'email' : 'NULL AS email';
    if ($isAdmin) {
        $stmt = $pdo->query("SELECT id, {$selectName}, {$selectEmail}, username, role, gym_id, created_at FROM users ORDER BY username");
    } else {
        if (!$sessionGymId) {
            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Gym context missing']); exit;
        }
        $stmt = $pdo->prepare("SELECT id, {$selectName}, {$selectEmail}, username, role, gym_id, created_at FROM users WHERE gym_id = ? ORDER BY username");
        $stmt->execute([$sessionGymId]);
    }
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true);
    $name = trim($d['name'] ?? '');
    $email = trim($d['email'] ?? '');
    $username = trim($d['username'] ?? '');
    $password = $d['password'] ?? '';
    $userRole = strtoupper(trim($d['role'] ?? 'OPERATORE'));
    $gymId    = !empty($d['gym_id']) ? (int)$d['gym_id'] : null;

    if (!$username || !$password) {
        http_response_code(400); echo json_encode(['success' => false, 'message' => 'Username e password obbligatori']); exit;
    }
    if (!$isAdmin) {
        if (!$sessionGymId) {
            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Gym context missing']); exit;
        }
        $userRole = 'OPERATORE';
        $gymId = $sessionGymId;
    }
    if ($userRole !== 'ADMIN' && $userRole !== 'SUPER' && !$gymId) {
        http_response_code(400); echo json_encode(['success' => false, 'message' => 'Per un gestore di sede la sede e obbligatoria']); exit;
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, username, password_hash, role, gym_id) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$name !== '' ? $name : null, $email !== '' ? $email : null, $username, $hash, $userRole, $gymId]);
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

    $existing = load_user_scope($pdo, $id);
    if (!$existing) {
        http_response_code(404); echo json_encode(['success' => false, 'message' => 'User not found']); exit;
    }

    if (!$isAdmin) {
        if (!$sessionGymId) {
            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Gym context missing']); exit;
        }
        if ((int)($existing['gym_id'] ?? 0) !== $sessionGymId) {
            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Forbidden']); exit;
        }
        $userRole = 'OPERATORE';
        $gymId = $sessionGymId;
    }

    if ($userRole !== 'ADMIN' && $userRole !== 'SUPER' && !$gymId) {
        http_response_code(400); echo json_encode(['success' => false, 'message' => 'Per un gestore di sede la sede e obbligatoria']); exit;
    }

    $name = trim($d['name'] ?? '');
    $email = trim($d['email'] ?? '');

    if ($password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, username=?, password_hash=?, role=?, gym_id=? WHERE id=?");
        $stmt->execute([$name !== '' ? $name : null, $email !== '' ? $email : null, $username, $hash, $userRole, $gymId, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, username=?, role=?, gym_id=? WHERE id=?");
        $stmt->execute([$name !== '' ? $name : null, $email !== '' ? $email : null, $username, $userRole, $gymId, $id]);
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($method === 'DELETE') {
    $parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
    $id = (int)end($parts);
    if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'ID mancante']); exit; }
    if (!$isAdmin) {
        if (!$sessionGymId) {
            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Gym context missing']); exit;
        }
        $existing = load_user_scope($pdo, $id);
        if (!$existing || (int)($existing['gym_id'] ?? 0) !== $sessionGymId) {
            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Forbidden']); exit;
        }
    }
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
