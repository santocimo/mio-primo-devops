<?php
// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../inc/validation.php';

$data = json_decode(file_get_contents('php://input'), true);

// Campi obbligatori
$gym_name     = trim($data['gym_name']     ?? '');
$gym_category = trim($data['gym_category'] ?? '');
$name         = trim($data['name']         ?? '');
$email        = trim($data['email']        ?? '');
$username     = trim($data['username']     ?? '');
$password     = $data['password']          ?? '';

// Validazioni
if (!$gym_name || !$gym_category || !$name || !$email || !$username || !$password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tutti i campi sono obbligatori']);
    exit;
}

if (!validate_gym_name($gym_name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nome struttura non valido (2-150 caratteri)']);
    exit;
}

$allowed_categories = ['gym', 'pilates', 'yoga', 'wellness', 'medical', 'studio'];
if (!in_array($gym_category, $allowed_categories, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tipo struttura non valido']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email non valida']);
    exit;
}

if (!validate_username($username)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username non valido (3-50 caratteri, lettere/numeri/.-_)']);
    exit;
}

if (!validate_password($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password troppo corta (minimo 8 caratteri)']);
    exit;
}

try {
    $pdo = getPDO();

    // Verifica username unico
    $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $chk->execute([$username]);
    if ($chk->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Username già in uso']);
        exit;
    }

    // Verifica email unica
    $chkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $chkEmail->execute([$email]);
    if ($chkEmail->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email già registrata']);
        exit;
    }

    $pdo->beginTransaction();

    // 1. Crea lo slug univoco per la struttura
    $base_slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $gym_name));
    $base_slug = trim($base_slug, '-');
    $slug = $base_slug;
    $i = 1;
    while (true) {
        $s = $pdo->prepare("SELECT id FROM gyms WHERE slug = ? LIMIT 1");
        $s->execute([$slug]);
        if (!$s->fetch()) break;
        $slug = $base_slug . '-' . $i++;
    }

    // 2. Inserisce la struttura
    $ins_gym = $pdo->prepare("INSERT INTO gyms (name, slug, category) VALUES (?, ?, ?)");
    $ins_gym->execute([$gym_name, $slug, $gym_category]);
    $gym_id = (int)$pdo->lastInsertId();

    // 3. Inserisce l'utente operatore (con trial attivo da subito)
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $ins_user = $pdo->prepare("INSERT INTO users (name, email, username, password_hash, role, gym_id, trial_start_date, subscription_status) VALUES (?, ?, ?, ?, 'operatore', ?, NOW(), 'trial')");
    $ins_user->execute([$name, $email, $username, $password_hash, $gym_id]);
    $user_id = (int)$pdo->lastInsertId();

    $pdo->commit();

    // 4. Genera token per auto-login
    $token = base64_encode(json_encode([
        'user_id'   => $user_id,
        'username'  => $username,
        'role'      => 'OPERATORE',
        'gym_id'    => $gym_id,
        'timestamp' => time()
    ]));

    echo json_encode([
        'success' => true,
        'message' => 'Registrazione completata',
        'user' => [
            'id'       => $user_id,
            'name'     => $name,
            'email'    => $email,
            'username' => $username,
            'role'     => 'operatore',
            'gym_id'   => $gym_id,
        ],
        'token' => $token
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore del server']);
}
