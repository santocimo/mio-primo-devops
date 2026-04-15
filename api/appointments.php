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
$user_id = (int)($_SESSION['user_id'] ?? 0);

function formatApt($a) {
    return [
        'id'               => (int)$a['id'],
        'service_id'       => (int)($a['service_id'] ?? 0),
        'gym_id'           => (int)($a['gym_id'] ?? 0),
        'customer_name'    => $a['customer_name'] ?? '',
        'customer_email'   => $a['customer_email'] ?? '',
        'scheduled_at'     => $a['scheduled_at'] ?? '',
        'status'           => $a['status'] ?? 'pending',
        'notes'            => $a['notes'] ?? '',
        'service_name'     => $a['service_name'] ?? '',
        'gym_name'         => $a['gym_name'] ?? '',
        'created_at'       => $a['created_at'] ?? '',
    ];
}

if ($method === 'GET') {
    if ($isAdmin) {
        $stmt = $pdo->query("SELECT a.*, s.name AS service_name, g.name AS gym_name FROM appointments a LEFT JOIN services s ON s.id=a.service_id LEFT JOIN gyms g ON g.id=s.gym_id ORDER BY a.scheduled_at DESC LIMIT 200");
    } else {
        $stmt = $pdo->prepare("SELECT a.*, s.name AS service_name, g.name AS gym_name FROM appointments a LEFT JOIN services s ON s.id=a.service_id LEFT JOIN gyms g ON g.id=s.gym_id WHERE a.gym_id=? ORDER BY a.scheduled_at DESC LIMIT 200");
        $gymId = (int)($_SESSION['gym_id'] ?? 1);
        $stmt->execute([$gymId]);
    }
    echo json_encode(array_map('formatApt', $stmt->fetchAll(PDO::FETCH_ASSOC)));
    exit;
}

if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true);
    $service_id     = (int)($d['service_id'] ?? 0);
    $customer_name  = trim($d['customer_name'] ?? '');
    $customer_email = trim($d['customer_email'] ?? '');
    $scheduled_at   = trim($d['scheduled_at'] ?? '');
    $status         = trim($d['status'] ?? 'pending');
    $notes          = trim($d['notes'] ?? '');
    // Ricava gym_id dal servizio
    $gymRow = $pdo->prepare("SELECT gym_id FROM services WHERE id=? LIMIT 1"); $gymRow->execute([$service_id]);
    $gym_id = (int)($gymRow->fetchColumn() ?: ($_SESSION['gym_id'] ?? 1));
    if (!$service_id || !$customer_name || !$scheduled_at) {
        http_response_code(400); echo json_encode(['success'=>false,'message'=>'Campi obbligatori mancanti']); exit;
    }
    $stmt = $pdo->prepare("INSERT INTO appointments (service_id,gym_id,customer_name,customer_email,scheduled_at,status,notes) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$service_id,$gym_id,$customer_name,$customer_email,$scheduled_at,$status,$notes]);
    echo json_encode(['success'=>true,'id'=>(int)$pdo->lastInsertId()]);
    exit;
}

if ($method === 'PUT') {
    $parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/'));
    $id = (int)end($parts);
    if (!$id) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'ID mancante']); exit; }
    $d = json_decode(file_get_contents('php://input'), true);
    $service_id     = (int)($d['service_id'] ?? 0);
    $customer_name  = trim($d['customer_name'] ?? '');
    $customer_email = trim($d['customer_email'] ?? '');
    $scheduled_at   = trim($d['scheduled_at'] ?? '');
    $status         = trim($d['status'] ?? 'pending');
    $notes          = trim($d['notes'] ?? '');
    $stmt = $pdo->prepare("UPDATE appointments SET service_id=?,customer_name=?,customer_email=?,scheduled_at=?,status=?,notes=? WHERE id=?");
    $stmt->execute([$service_id,$customer_name,$customer_email,$scheduled_at,$status,$notes,$id]);
    echo json_encode(['success'=>true]);
    exit;
}

if ($method === 'DELETE') {
    $parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/'));
    $id = (int)end($parts);
    if (!$id) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'ID mancante']); exit; }
    $pdo->prepare("DELETE FROM appointments WHERE id=?")->execute([$id]);
    echo json_encode(['success'=>true]);
    exit;
}

http_response_code(405);
echo json_encode(['success'=>false,'message'=>'Method not allowed']);
?>
