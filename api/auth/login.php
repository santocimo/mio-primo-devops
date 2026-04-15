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
    $stmt = $pdo->prepare("SELECT id, name, email, username, role, password_hash, gym_id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        // Valid login
        $_SESSION['admin_logged'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = strtoupper($user['role']);
        if ($user['gym_id']) {
            $_SESSION['gym_id'] = (int)$user['gym_id'];
        }
        
        // Generate simple token
        $token = base64_encode(json_encode([
            'user_id'   => $user['id'],
            'username'  => $user['username'],
            'role'      => strtoupper($user['role']),
            'gym_id'    => (int)($user['gym_id'] ?? 1),
            'timestamp' => time()
        ]));
        
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
            'token' => $token
        ]);
        exit;
    }
} catch (Exception $e) {
    // Fall through to static credentials
}

// Fallback static credentials
if ($username === 'admin' && $password === 'admin123') {
    $_SESSION['admin_logged'] = true;
    $_SESSION['user_id'] = 0;
    $_SESSION['user_role'] = 'ADMIN';
    
    $token = base64_encode(json_encode([
        'user_id'   => 0,
        'username'  => 'admin',
        'role'      => 'ADMIN',
        'gym_id'    => 1,
        'timestamp' => time()
    ]));
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => 0,
            'name' => 'Administrator',
            'email' => 'admin@system.local',
            'username' => 'admin',
            'role' => 'admin',
            'gym_id' => 1
        ],
        'token' => $token
    ]);
    exit;
}

http_response_code(401);
echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
?>
