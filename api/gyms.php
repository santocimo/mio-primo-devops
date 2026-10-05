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
if (!verify_bearer_token()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require_active_api_subscription();

$pdo = getPDO();
$method = $_SERVER['REQUEST_METHOD'];
$role = strtoupper($_SESSION['user_role'] ?? '');
$isAdmin = strpos($role, 'ADMIN') !== false || strpos($role, 'SUPER') !== false;

if ($method === 'GET') {
    $allowedCategories = ['gym', 'salon', 'studio', 'other'];
    $configuredCategory = trim((string)getAppSetting($pdo, 'default_business_type', 'gym'));
    if (!in_array($configuredCategory, $allowedCategories, true)) {
        $configuredCategory = 'gym';
    }

    $requestedCategory = trim((string)($_GET['category'] ?? ''));
    if ($requestedCategory !== '' && in_array($requestedCategory, $allowedCategories, true)) {
        $configuredCategory = $requestedCategory;
    }

    $allCategories = isset($_GET['all_categories']) && $_GET['all_categories'] === '1';
    $currentGymId = !empty($_SESSION['gym_id']) ? (int)$_SESSION['gym_id'] : null;
    $profileFields = ", JSON_UNQUOTE(JSON_EXTRACT(COALESCE(settings, '{}'), '$.address')) AS address, JSON_UNQUOTE(JSON_EXTRACT(COALESCE(settings, '{}'), '$.city')) AS city, JSON_UNQUOTE(JSON_EXTRACT(COALESCE(settings, '{}'), '$.phone')) AS phone, JSON_UNQUOTE(JSON_EXTRACT(COALESCE(settings, '{}'), '$.manager_name')) AS manager_name, JSON_UNQUOTE(JSON_EXTRACT(COALESCE(settings, '{}'), '$.manager_email')) AS manager_email, JSON_UNQUOTE(JSON_EXTRACT(COALESCE(settings, '{}'), '$.manager_username')) AS manager_username, JSON_UNQUOTE(JSON_EXTRACT(COALESCE(settings, '{}'), '$.manager_cf')) AS manager_cf, JSON_UNQUOTE(JSON_EXTRACT(COALESCE(settings, '{}'), '$.activity_name')) AS activity_name";

    if (!$isAdmin) {
        if ($currentGymId) {
            $stmt = $pdo->prepare("SELECT id, name, slug, category, created_at{$profileFields} FROM gyms WHERE id = ? LIMIT 1");
            $stmt->execute([$currentGymId]);
        } else {
            echo json_encode([]);
            exit;
        }
    } elseif ($allCategories) {
        $stmt = $pdo->prepare("SELECT id, name, slug, category, created_at{$profileFields} FROM gyms ORDER BY name LIMIT 200");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("SELECT id, name, slug, category, created_at{$profileFields} FROM gyms WHERE category = ? ORDER BY name LIMIT 200");
        $stmt->execute([$configuredCategory]);
    }

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    exit;
}

// Operators may update only their own gym profile.
$operatorGymId = (int)($_SESSION['gym_id'] ?? 0);
$isOperator = strpos($role, 'OPERATORE') !== false || strpos($role, 'OPERATOR') !== false;
$canUpdateOwnGym = $method === 'PUT' && $isOperator && $operatorGymId > 0;
if ($method !== 'GET' && $method !== 'POST' && !$isAdmin && !$canUpdateOwnGym) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }

if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true);
    $name     = trim($d['name'] ?? '');
    $slug     = trim($d['slug'] ?? strtolower(preg_replace('/[^a-z0-9]+/','-',$name)));
    $category = trim($d['category'] ?? 'gym');
    if (!$name) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Name obbligatorio']); exit; }

    if ($isAdmin) {
        $stmt = $pdo->prepare("INSERT INTO gyms (name,slug,category) VALUES (?,?,?)");
        $stmt->execute([$name,$slug,$category]);
        echo json_encode(['success'=>true,'id'=>(int)$pdo->lastInsertId()]);
        exit;
    }

    // Non-admin: create gym and attach manager info + trial start in settings
    $managerId = (int)($_SESSION['user_id'] ?? 0);
    $managerUsername = $_SESSION['username'] ?? '';
    $managerEmail = $_SESSION['email'] ?? '';
    $settings = json_encode([
        'manager_user_id' => $managerId,
        'manager_username' => $managerUsername,
        'manager_email' => $managerEmail,
        'trial_started_at' => date('c'),
    ]);

    $stmt = $pdo->prepare("INSERT INTO gyms (name,slug,category,settings) VALUES (?,?,?,?)");
    $stmt->execute([$name,$slug,$category,$settings]);
    $newId = (int)$pdo->lastInsertId();

    // Assign the created gym to the current user (operator)
    if ($managerId > 0) {
        $u = $pdo->prepare("UPDATE users SET gym_id=? WHERE id=?");
        $u->execute([$newId, $managerId]);
    }

    echo json_encode(['success'=>true,'id'=>$newId]);
    exit;
}

if ($method === 'PUT') {
    $parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/'));
    $id = (int)end($parts);
    if (!$id) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'ID mancante']); exit; }

    if (!$isAdmin && $id !== $operatorGymId) {
        http_response_code(403);
        echo json_encode(['success'=>false,'message'=>'Puoi modificare solo la tua sede']);
        exit;
    }

    $d = json_decode(file_get_contents('php://input'), true);
    $name     = trim($d['name'] ?? '');
    $category = trim($d['category'] ?? 'gym');

    if ($isAdmin) {
        $existingStmt = $pdo->prepare("SELECT slug, settings FROM gyms WHERE id=? LIMIT 1");
        $existingStmt->execute([$id]);
        $existingGym = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existingGym) {
            http_response_code(404);
            echo json_encode(['success'=>false,'message'=>'Attività non trovata']);
            exit;
        }

        $allowedCategories = ['gym', 'salon', 'studio', 'other'];
        if ($name === '' || strlen($name) > 150 || !in_array($category, $allowedCategories, true)) {
            http_response_code(400);
            echo json_encode(['success'=>false,'message'=>'Nome attività o categoria non validi']);
            exit;
        }

        $slug = trim((string)($d['slug'] ?? $existingGym['slug'] ?? ''));
        if ($slug === '') {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
        }
        $settings = json_decode((string)($existingGym['settings'] ?: '{}'), true);
        if (!is_array($settings)) $settings = [];

        $profileSubmitted = array_key_exists('manager_name', $d) || array_key_exists('manager_email', $d);
        if ($profileSubmitted) {
            $managerName = trim($d['manager_name'] ?? '');
            $managerEmail = trim($d['manager_email'] ?? '');
            $managerCf = strtoupper(trim($d['manager_cf'] ?? ''));
            $address = trim($d['address'] ?? '');
            $city = trim($d['city'] ?? '');
            $phone = trim($d['phone'] ?? '');
            $activityName = trim($d['activity_name'] ?? '');

            if ($managerName === '' || strlen($managerName) > 160 || !filter_var($managerEmail, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['success'=>false,'message'=>'Nome ed email del gestore non validi']);
                exit;
            }
            if ($managerCf !== '' && !preg_match('/^[A-Z0-9]{16}$/', $managerCf)) {
                http_response_code(400);
                echo json_encode(['success'=>false,'message'=>'Codice fiscale non valido']);
                exit;
            }
            if (strlen($address) > 200 || strlen($city) > 120 || strlen($phone) > 30 || strlen($activityName) > 150) {
                http_response_code(400);
                echo json_encode(['success'=>false,'message'=>'Uno o più campi superano la lunghezza consentita']);
                exit;
            }

            $settings = array_merge($settings, [
                'address' => $address,
                'city' => $city,
                'phone' => $phone,
                'activity_name' => $activityName,
                'manager_name' => $managerName,
                'manager_email' => $managerEmail,
                'manager_cf' => $managerCf,
            ]);
        }

        $stmt = $pdo->prepare("UPDATE gyms SET name=?,slug=?,category=?,settings=? WHERE id=?");
        $stmt->execute([$name,$slug,$category,json_encode($settings, JSON_UNESCAPED_UNICODE),$id]);
        echo json_encode(['success'=>true]);
        exit;
    }

    $allowedCategories = ['gym', 'salon', 'studio', 'other'];
    $managerName = trim($d['manager_name'] ?? '');
    $managerEmail = trim($d['manager_email'] ?? '');
    $managerCf = strtoupper(trim($d['manager_cf'] ?? ''));
    $address = trim($d['address'] ?? '');
    $city = trim($d['city'] ?? '');
    $phone = trim($d['phone'] ?? '');
    $activityName = trim($d['activity_name'] ?? '');
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $currentUserEmail = '';
    if ($userId > 0) {
        $userEmailQuery = $pdo->prepare("SELECT email FROM users WHERE id=? AND gym_id=? LIMIT 1");
        $userEmailQuery->execute([$userId,$id]);
        $currentUserEmail = (string)($userEmailQuery->fetchColumn() ?: '');
    }
    $emailValid = filter_var($managerEmail, FILTER_VALIDATE_EMAIL)
        || ($managerEmail !== '' && strcasecmp($managerEmail, $currentUserEmail) === 0);

    if ($name === '' || strlen($name) > 150 || !in_array($category, $allowedCategories, true)) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Nome attività o categoria non validi']);
        exit;
    }
    if ($managerName === '' || strlen($managerName) > 160 || !$emailValid) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Nome ed email del gestore sono obbligatori e devono essere validi']);
        exit;
    }
    if ($managerCf !== '' && !preg_match('/^[A-Z0-9]{16}$/', $managerCf)) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Codice fiscale non valido']);
        exit;
    }
    if (strlen($address) > 200 || strlen($city) > 120 || strlen($phone) > 30 || strlen($activityName) > 150) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Uno o più campi superano la lunghezza consentita']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT settings FROM gyms WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $settings = json_decode((string)($stmt->fetchColumn() ?: '{}'), true);
    if (!is_array($settings)) $settings = [];
    $settings = array_merge($settings, [
        'address' => $address,
        'city' => $city,
        'phone' => $phone,
        'activity_name' => $activityName,
        'manager_name' => $managerName,
        'manager_email' => $managerEmail,
        'manager_username' => $_SESSION['username'] ?? ($settings['manager_username'] ?? ''),
        'manager_cf' => $managerCf,
    ]);

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE gyms SET name=?,category=?,settings=? WHERE id=?");
    $stmt->execute([$name,$category,json_encode($settings, JSON_UNESCAPED_UNICODE),$id]);
    if ($userId > 0) {
        $stmt = $pdo->prepare("UPDATE users SET name=?,email=? WHERE id=? AND gym_id=?");
        $stmt->execute([$managerName,$managerEmail,$userId,$id]);
    }
    $pdo->commit();
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
