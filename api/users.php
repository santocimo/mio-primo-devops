<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../inc/security.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth/verify_token.php';
require_once __DIR__ . '/../inc/recurring_subscriptions.php';

if (!verify_bearer_token()) {
    http_response_code(401); echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}
require_active_api_subscription();

$role = strtoupper($_SESSION['user_role'] ?? '');
if (strpos($role, 'ADMIN') === false && strpos($role, 'SUPER') === false
    && strpos($role, 'OPERATORE') === false && $role !== 'GESTORE') {
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
    $selectName = users_table_has_column($pdo, 'name') ? 'u.name' : 'NULL AS name';
    $selectEmail = users_table_has_column($pdo, 'email') ? 'u.email' : 'NULL AS email';
    if ($isAdmin) {
        $stmt = $pdo->query(
            "SELECT u.id, {$selectName}, {$selectEmail}, u.username, u.role, u.gym_id, u.created_at,
                    (g.billing_owner_user_id = u.id) AS is_billing_owner
             FROM users u LEFT JOIN gyms g ON g.id = u.gym_id ORDER BY u.username"
        );
    } else {
        if (!$sessionGymId) {
            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Gym context missing']); exit;
        }
        $stmt = $pdo->prepare(
            "SELECT u.id, {$selectName}, {$selectEmail}, u.username, u.role, u.gym_id, u.created_at,
                    (g.billing_owner_user_id = u.id) AS is_billing_owner
             FROM users u LEFT JOIN gyms g ON g.id = u.gym_id
             WHERE u.gym_id = ? ORDER BY u.username"
        );
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

    $isBillingOwner = filter_var($d['is_billing_owner'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if (!$username || !$password) {
        http_response_code(400); echo json_encode(['success' => false, 'message' => 'Username e password obbligatori']); exit;
    }
    if (!$isAdmin) {
        if (!$sessionGymId) {
            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Gym context missing']); exit;
        }
        if (!in_array($userRole, ['OPERATORE', 'GESTORE'], true)) {
            http_response_code(400); echo json_encode(['success' => false, 'message' => 'Ruolo non consentito']); exit;
        }
        $gymId = $sessionGymId;
    }
    if (!in_array($userRole, ['ADMIN', 'SUPER', 'OPERATORE', 'GESTORE'], true)) {
        http_response_code(400); echo json_encode(['success' => false, 'message' => 'Ruolo non valido']); exit;
    }
    if ($userRole !== 'ADMIN' && $userRole !== 'SUPER' && !$gymId) {
        http_response_code(400); echo json_encode(['success' => false, 'message' => 'Per un gestore di sede la sede e obbligatoria']); exit;
    }
    if ($userRole === 'GESTORE') {
        $isBillingOwner = true;
    }
    if ($isBillingOwner && !$gymId) {
        http_response_code(400); echo json_encode(['success' => false, 'message' => 'Il referente deve appartenere a una palestra']); exit;
    }
    if ($isBillingOwner) {
        if (!$isAdmin && !is_gym_billing_owner($pdo, (int)($_SESSION['user_id'] ?? 0), (int)$gymId)) {
            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Solo il referente attuale può trasferire la fatturazione']); exit;
        }
        $membership = $pdo->prepare('SELECT id FROM gyms WHERE id = ?');
        $membership->execute([$gymId]);
        if (!$membership->fetchColumn()) {
            http_response_code(400); echo json_encode(['success' => false, 'message' => 'Palestra non valida']); exit;
        }
    }
    if ($userRole === 'GESTORE' && !$gymId) {
        http_response_code(400); echo json_encode(['success' => false, 'message' => 'Il referente deve appartenere a una palestra']); exit;
    }
    $pdo->beginTransaction();
    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, username, password_hash, role, gym_id) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$name !== '' ? $name : null, $email !== '' ? $email : null, $username, $hash, $userRole, $gymId]);
        $id = (int)$pdo->lastInsertId();
        if ($isBillingOwner) {
            $owner = $pdo->prepare('UPDATE gyms SET billing_owner_user_id = ? WHERE id = ?');
            $owner->execute([$id, $gymId]);
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $id]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
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
    $isBillingOwner = filter_var($d['is_billing_owner'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $existing = load_user_scope($pdo, $id);
    if (!$existing) {
        http_response_code(404); echo json_encode(['success' => false, 'message' => 'User not found']); exit;
    }
    $existingOwner = $pdo->prepare(
        'SELECT id FROM gyms WHERE billing_owner_user_id = ? AND id = ? LIMIT 1'
    );
    $existingOwner->execute([$id, (int)$existing['gym_id']]);
    $isExistingBillingOwner = (bool)$existingOwner->fetchColumn();

    if (!$isAdmin) {
        if (!$sessionGymId) {
            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Gym context missing']); exit;
        }
        if ((int)($existing['gym_id'] ?? 0) !== $sessionGymId) {
            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Forbidden']); exit;
        }
        if (!in_array($userRole, ['OPERATORE', 'GESTORE'], true)) {
            http_response_code(400); echo json_encode(['success' => false, 'message' => 'Ruolo non consentito']); exit;
        }
        $gymId = $sessionGymId;
    }

    if (!in_array($userRole, ['ADMIN', 'SUPER', 'OPERATORE', 'GESTORE'], true)) {
        http_response_code(400); echo json_encode(['success' => false, 'message' => 'Ruolo non valido']); exit;
    }
    if ($userRole !== 'ADMIN' && $userRole !== 'SUPER' && !$gymId) {
        http_response_code(400); echo json_encode(['success' => false, 'message' => 'Per un gestore di sede la sede e obbligatoria']); exit;
    }

    $name = trim($d['name'] ?? '');
    $email = trim($d['email'] ?? '');

    if ($userRole === 'GESTORE') {
        $isBillingOwner = true;
    }
    if ($isBillingOwner && !$gymId) {
        http_response_code(400); echo json_encode(['success' => false, 'message' => 'Il referente deve appartenere a una palestra']); exit;
    }
    if ($isBillingOwner) {
        if (!$isAdmin && !is_gym_billing_owner($pdo, (int)($_SESSION['user_id'] ?? 0), (int)$gymId)) {
            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Solo il referente attuale può trasferire la fatturazione']); exit;
        }
        $membership = $pdo->prepare('SELECT id FROM gyms WHERE id = ?');
        $membership->execute([$gymId]);
        if (!$membership->fetchColumn()) {
            http_response_code(400); echo json_encode(['success' => false, 'message' => 'Palestra non valida']); exit;
        }
        if ($id !== (int)$existing['id']) {
            $targetMembership = $pdo->prepare('SELECT id FROM users WHERE id = ? AND gym_id = ?');
            $targetMembership->execute([$id, $gymId]);
            if (!$targetMembership->fetchColumn()) {
                http_response_code(400); echo json_encode(['success' => false, 'message' => 'Il referente deve appartenere alla palestra selezionata']); exit;
            }
        }
    }
    if ($isExistingBillingOwner && (!$isBillingOwner || (int)$gymId !== (int)$existing['gym_id'])) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Assegna prima un altro referente di fatturazione']);
        exit;
    }
    if ($userRole === 'GESTORE' && !$gymId) {
        http_response_code(400); echo json_encode(['success' => false, 'message' => 'Il referente deve appartenere a una palestra']); exit;
    }
    $pdo->beginTransaction();
    try {
        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, username=?, password_hash=?, role=?, gym_id=? WHERE id=?");
            $stmt->execute([$name !== '' ? $name : null, $email !== '' ? $email : null, $username, $hash, $userRole, $gymId, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, username=?, role=?, gym_id=? WHERE id=?");
            $stmt->execute([$name !== '' ? $name : null, $email !== '' ? $email : null, $username, $userRole, $gymId, $id]);
        }
        if ($isBillingOwner) {
            $owner = $pdo->prepare('UPDATE gyms SET billing_owner_user_id = ? WHERE id = ?');
            $owner->execute([$id, $gymId]);
        } else {
            $clearOwner = $pdo->prepare(
                'UPDATE gyms SET billing_owner_user_id = NULL WHERE id = ? AND billing_owner_user_id = ?'
            );
            $clearOwner->execute([(int)$existing['gym_id'], $id]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
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
    $owner = $pdo->prepare('SELECT id FROM gyms WHERE billing_owner_user_id = ? LIMIT 1');
    $owner->execute([$id]);
    if ($owner->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Trasferisci prima il ruolo di referente a un altro account']);
        exit;
    }
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
