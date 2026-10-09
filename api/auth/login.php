<?php
// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../inc/security.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../inc/subscription.php';
require_once __DIR__ . '/../../inc/api_token.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['username'], $data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password required']);
    exit;
}

$username = $data['username'];
$password = $data['password'];

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id, name, email, username, role, password_hash, gym_id, trial_start_date, subscription_status, subscription_plan, subscription_expires_at FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $token = create_api_token($user);
        session_regenerate_id(true);
        $_SESSION['admin_logged'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = strtoupper($user['role']);
        $_SESSION['gym_id'] = $user['gym_id'] !== null ? (int)$user['gym_id'] : null;
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'username' => $user['username'],
                'role' => strtolower($user['role']),
                'gym_id' => $user['gym_id']
            ],
            'token' => $token,
            'subscription' => compute_gym_subscription($user, $pdo)
        ]);
        exit;
    }
} catch (Throwable $e) {
    error_log('Login failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to authenticate']);
    exit;
}

http_response_code(401);
echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
?>
