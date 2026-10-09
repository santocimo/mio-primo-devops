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

// Accetta sia sessione PHP (web) che Bearer token (app mobile).
// Il Bearer token ha priorita assoluta, anche se la sessione PHP precedente e' stale.
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
$isOperator = strpos($role, 'OPERATORE') !== false || strpos($role, 'OPERATOR') !== false || $role === 'GESTORE';
$user_id = (int)($_SESSION['user_id'] ?? 0);

$appointmentsHasGymId = false;
$appointmentsHasContactId = false;
try {
    $col = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = DATABASE() AND table_name = 'appointments' AND column_name = 'gym_id'");
    $col->execute();
    $appointmentsHasGymId = (bool)$col->fetchColumn();
} catch (Exception $e) {
    $appointmentsHasGymId = false;
}
try {
    $col = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = DATABASE() AND table_name = 'appointments' AND column_name = 'contact_id'");
    $col->execute();
    $appointmentsHasContactId = (bool)$col->fetchColumn();
} catch (Exception $e) {
    $appointmentsHasContactId = false;
}
$aptGymExpr = $appointmentsHasGymId ? 'a.gym_id' : 's.gym_id';

function formatApt($a) {
    return [
        'id'                   => (int)$a['id'],
        'service_id'           => (int)($a['service_id'] ?? 0),
        'contact_id'           => isset($a['contact_id']) ? (int)$a['contact_id'] : null,
        'gym_id'               => (int)($a['gym_id'] ?? $a['service_gym_id'] ?? 0),
        'customer_name'        => $a['customer_name'] ?? '',
        'customer_email'       => $a['customer_email'] ?? '',
        'scheduled_at'         => $a['scheduled_at'] ?? '',
        'status'               => $a['status'] ?? 'pending',
        'notes'                => $a['notes'] ?? '',
        'service_name'         => $a['service_name'] ?? '',
        'service_provider_name' => $a['service_provider_name'] ?? null,
        'service_provider_type' => $a['service_provider_type'] ?? 'internal',
        'gym_name'             => $a['gym_name'] ?? '',
        'created_at'           => $a['created_at'] ?? '',
    ];
}

if ($method === 'GET') {
    $selectedGymId = null;
    $selectedServiceId = null;
    // Only allow admin to request arbitrary gym_id via query param
    if ($isAdmin && isset($_GET['gym_id']) && (int)$_GET['gym_id'] > 0) {
        $selectedGymId = (int)$_GET['gym_id'];
    } elseif (!empty($_SESSION['gym_id'])) {
        $selectedGymId = (int)$_SESSION['gym_id'];
    }
    if (isset($_GET['service_id']) && (int)$_GET['service_id'] > 0) {
        $selectedServiceId = (int)$_GET['service_id'];
    }

    if ($isAdmin && $selectedGymId === null) {
        if ($selectedServiceId !== null) {
            $stmt = $pdo->prepare("SELECT a.*, s.gym_id AS service_gym_id, s.name AS service_name, s.provider_name AS service_provider_name, s.provider_type AS service_provider_type, g.name AS gym_name FROM appointments a LEFT JOIN services s ON s.id=a.service_id LEFT JOIN gyms g ON g.id=s.gym_id WHERE a.service_id=? ORDER BY a.scheduled_at DESC LIMIT 200");
            $stmt->execute([$selectedServiceId]);
        } else {
            $stmt = $pdo->query("SELECT a.*, s.gym_id AS service_gym_id, s.name AS service_name, s.provider_name AS service_provider_name, s.provider_type AS service_provider_type, g.name AS gym_name FROM appointments a LEFT JOIN services s ON s.id=a.service_id LEFT JOIN gyms g ON g.id=s.gym_id ORDER BY a.scheduled_at DESC LIMIT 200");
        }
    } else {
        $gymId = $selectedGymId ?? (int)($_SESSION['gym_id'] ?? 1);
        if ($selectedServiceId !== null) {
            $stmt = $pdo->prepare("SELECT a.*, s.gym_id AS service_gym_id, s.name AS service_name, s.provider_name AS service_provider_name, s.provider_type AS service_provider_type, g.name AS gym_name FROM appointments a LEFT JOIN services s ON s.id=a.service_id LEFT JOIN gyms g ON g.id=s.gym_id WHERE {$aptGymExpr}=? AND a.service_id=? ORDER BY a.scheduled_at DESC LIMIT 200");
            $stmt->execute([$gymId, $selectedServiceId]);
        } else {
            $stmt = $pdo->prepare("SELECT a.*, s.gym_id AS service_gym_id, s.name AS service_name, s.provider_name AS service_provider_name, s.provider_type AS service_provider_type, g.name AS gym_name FROM appointments a LEFT JOIN services s ON s.id=a.service_id LEFT JOIN gyms g ON g.id=s.gym_id WHERE {$aptGymExpr}=? ORDER BY a.scheduled_at DESC LIMIT 200");
            $stmt->execute([$gymId]);
        }
    }
    echo json_encode(array_map('formatApt', $stmt->fetchAll(PDO::FETCH_ASSOC)));
    exit;
}

if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true);
    $service_id     = (int)($d['service_id'] ?? 0);
    $contact_id     = (int)($d['contact_id'] ?? 0);
    $customer_name  = trim($d['customer_name'] ?? '');
    $customer_email = trim($d['customer_email'] ?? '');
    $scheduled_at   = trim($d['scheduled_at'] ?? '');
    $status         = $isAdmin ? trim($d['status'] ?? 'pending') : 'pending';
    $notes          = trim($d['notes'] ?? '');
    if (!in_array($status, ['pending', 'confirmed', 'scheduled', 'completed', 'cancelled'], true)) {
        http_response_code(400); echo json_encode(['success'=>false,'message'=>'Stato appuntamento non valido']); exit;
    }
    $scheduled_at = str_replace('T', ' ', $scheduled_at);
    if (strlen($scheduled_at) === 16) $scheduled_at .= ':00';

    if (!$service_id || !$scheduled_at || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $scheduled_at)) {
        http_response_code(400); echo json_encode(['success'=>false,'message'=>'Servizio e data/ora validi sono obbligatori']); exit;
    }

    $pdo->beginTransaction();
    $serviceStmt = $pdo->prepare("SELECT id, gym_id, capacity FROM services WHERE id=? FOR UPDATE");
    $serviceStmt->execute([$service_id]);
    $service = $serviceStmt->fetch(PDO::FETCH_ASSOC);
    if (!$service) {
        $pdo->rollBack();
        http_response_code(404); echo json_encode(['success'=>false,'message'=>'Servizio non trovato']); exit;
    }
    $gym_id = (int)$service['gym_id'];
    $sessionGymId = (int)($_SESSION['gym_id'] ?? 0);
    if (!$isAdmin && (!$sessionGymId || $sessionGymId !== $gym_id)) {
        $pdo->rollBack();
        http_response_code(403); echo json_encode(['success'=>false,'message'=>'Il servizio non appartiene alla tua palestra']); exit;
    }

    if ($contact_id > 0) {
        $contactStmt = $pdo->prepare("SELECT id, nome, cognome, gym_id FROM visitatori WHERE id=? LIMIT 1");
        $contactStmt->execute([$contact_id]);
        $contact = $contactStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contact || (int)$contact['gym_id'] !== $gym_id) {
            $pdo->rollBack();
            http_response_code(400); echo json_encode(['success'=>false,'message'=>'Iscritto e servizio devono appartenere alla stessa palestra']); exit;
        }
        $customer_name = trim(($contact['nome'] ?? '') . ' ' . ($contact['cognome'] ?? ''));
    } elseif (!$isAdmin || $customer_name === '') {
        $pdo->rollBack();
        http_response_code(400); echo json_encode(['success'=>false,'message'=>'Seleziona un iscritto']); exit;
    }

    $capacity = max(1, (int)$service['capacity']);
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE service_id=? AND scheduled_at=? AND status IN ('pending','confirmed','scheduled')");
    $countStmt->execute([$service_id, $scheduled_at]);
    if ((int)$countStmt->fetchColumn() >= $capacity) {
        $pdo->rollBack();
        http_response_code(409); echo json_encode(['success'=>false,'message'=>'Non ci sono più posti disponibili per questo orario']); exit;
    }

    if ($contact_id > 0 && $appointmentsHasContactId) {
        $duplicateStmt = $pdo->prepare("SELECT id FROM appointments WHERE contact_id=? AND service_id=? AND scheduled_at=? AND status IN ('pending','confirmed','scheduled') LIMIT 1");
        $duplicateStmt->execute([$contact_id,$service_id,$scheduled_at]);
        if ($duplicateStmt->fetch()) {
            $pdo->rollBack();
            http_response_code(409); echo json_encode(['success'=>false,'message'=>'Questo iscritto ha già prenotato questo servizio a quest’ora']); exit;
        }
    }

    if ($appointmentsHasContactId && $appointmentsHasGymId) {
        $stmt = $pdo->prepare("INSERT INTO appointments (service_id,gym_id,contact_id,customer_name,customer_email,scheduled_at,status,notes) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$service_id,$gym_id,$contact_id > 0 ? $contact_id : null,$customer_name,$customer_email,$scheduled_at,$status,$notes]);
    } elseif ($appointmentsHasContactId) {
        $stmt = $pdo->prepare("INSERT INTO appointments (service_id,contact_id,customer_name,customer_email,scheduled_at,status,notes) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$service_id,$contact_id > 0 ? $contact_id : null,$customer_name,$customer_email,$scheduled_at,$status,$notes]);
    } elseif ($appointmentsHasGymId) {
        $stmt = $pdo->prepare("INSERT INTO appointments (service_id,gym_id,customer_name,customer_email,scheduled_at,status,notes) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$service_id,$gym_id,$customer_name,$customer_email,$scheduled_at,$status,$notes]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO appointments (service_id,customer_name,customer_email,scheduled_at,status,notes) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$service_id,$customer_name,$customer_email,$scheduled_at,$status,$notes]);
    }
    $appointmentId = (int)$pdo->lastInsertId();
    $pdo->commit();
    echo json_encode(['success'=>true,'id'=>$appointmentId,'appointment_id'=>$appointmentId]);
    exit;
}

if ($method === 'PUT') {
    $parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/'));
    $id = (int)end($parts);
    if (!$id) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'ID mancante']); exit; }
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$isAdmin && !$isOperator) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }

    $pdo->beginTransaction();
    $existingStmt = $pdo->prepare("SELECT a.*, {$aptGymExpr} AS appointment_gym_id FROM appointments a LEFT JOIN services s ON s.id=a.service_id WHERE a.id=? LIMIT 1 FOR UPDATE");
    $existingStmt->execute([$id]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        $pdo->rollBack();
        http_response_code(404); echo json_encode(['success'=>false,'message'=>'Prenotazione non trovata']); exit;
    }

    $appointmentGymId = (int)($existing['appointment_gym_id'] ?? 0);
    $sessionGymId = (int)($_SESSION['gym_id'] ?? 0);
    if (!$isAdmin && (!$sessionGymId || $appointmentGymId !== $sessionGymId)) {
        $pdo->rollBack();
        http_response_code(403); echo json_encode(['success'=>false,'message'=>'Puoi modificare solo le prenotazioni della tua palestra']); exit;
    }

    $service_id = (int)($d['service_id'] ?? $existing['service_id']);
    $contact_id = array_key_exists('contact_id', $d) ? (int)$d['contact_id'] : (int)($existing['contact_id'] ?? 0);
    $customer_name = trim($d['customer_name'] ?? $existing['customer_name'] ?? '');
    $customer_email = trim($d['customer_email'] ?? $existing['customer_email'] ?? '');
    $scheduled_at = str_replace('T', ' ', trim($d['scheduled_at'] ?? $existing['scheduled_at'] ?? ''));
    if (strlen($scheduled_at) === 16) $scheduled_at .= ':00';
    $status = trim($d['status'] ?? $existing['status'] ?? 'pending');
    $notes = trim($d['notes'] ?? $existing['notes'] ?? '');

    if (!in_array($status, ['pending', 'confirmed', 'scheduled', 'completed', 'cancelled'], true)) {
        $pdo->rollBack();
        http_response_code(400); echo json_encode(['success'=>false,'message'=>'Stato appuntamento non valido']); exit;
    }
    if (!$service_id || !$scheduled_at || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $scheduled_at)) {
        $pdo->rollBack();
        http_response_code(400); echo json_encode(['success'=>false,'message'=>'Servizio e data/ora validi sono obbligatori']); exit;
    }

    $serviceStmt = $pdo->prepare("SELECT id, gym_id, capacity FROM services WHERE id=? FOR UPDATE");
    $serviceStmt->execute([$service_id]);
    $service = $serviceStmt->fetch(PDO::FETCH_ASSOC);
    if (!$service || (int)$service['gym_id'] !== $appointmentGymId) {
        $pdo->rollBack();
        http_response_code(400); echo json_encode(['success'=>false,'message'=>'Il servizio deve appartenere alla stessa palestra della prenotazione']); exit;
    }

    if (!$isAdmin && (int)$service['gym_id'] !== $sessionGymId) {
        $pdo->rollBack();
        http_response_code(403); echo json_encode(['success'=>false,'message'=>'Il servizio non appartiene alla tua palestra']); exit;
    }

    if ($contact_id > 0) {
        $contactStmt = $pdo->prepare("SELECT id, nome, cognome, gym_id FROM visitatori WHERE id=? LIMIT 1");
        $contactStmt->execute([$contact_id]);
        $contact = $contactStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contact || (int)$contact['gym_id'] !== (int)$service['gym_id']) {
            $pdo->rollBack();
            http_response_code(400); echo json_encode(['success'=>false,'message'=>'Iscritto e servizio devono appartenere alla stessa palestra']); exit;
        }
        $customer_name = trim(($contact['nome'] ?? '') . ' ' . ($contact['cognome'] ?? ''));
    } elseif ($customer_name === '') {
        $pdo->rollBack();
        http_response_code(400); echo json_encode(['success'=>false,'message'=>'Seleziona un iscritto']); exit;
    }

    if (in_array($status, ['pending', 'confirmed', 'scheduled'], true)) {
        $capacityStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE service_id=? AND scheduled_at=? AND status IN ('pending','confirmed','scheduled') AND id<>?");
        $capacityStmt->execute([$service_id, $scheduled_at, $id]);
        if ((int)$capacityStmt->fetchColumn() >= max(1, (int)$service['capacity'])) {
            $pdo->rollBack();
            http_response_code(409); echo json_encode(['success'=>false,'message'=>'Non ci sono più posti disponibili per questo orario']); exit;
        }
    }

    if ($contact_id > 0 && $appointmentsHasContactId && in_array($status, ['pending', 'confirmed', 'scheduled'], true)) {
        $duplicateStmt = $pdo->prepare("SELECT id FROM appointments WHERE contact_id=? AND service_id=? AND scheduled_at=? AND status IN ('pending','confirmed','scheduled') AND id<>? LIMIT 1");
        $duplicateStmt->execute([$contact_id, $service_id, $scheduled_at, $id]);
        if ($duplicateStmt->fetch()) {
            $pdo->rollBack();
            http_response_code(409); echo json_encode(['success'=>false,'message'=>'Questo iscritto ha già prenotato questo servizio a quest’ora']); exit;
        }
    }

    if ($appointmentsHasContactId && $appointmentsHasGymId) {
        $stmt = $pdo->prepare("UPDATE appointments SET service_id=?,gym_id=?,contact_id=?,customer_name=?,customer_email=?,scheduled_at=?,status=?,notes=? WHERE id=?");
        $stmt->execute([$service_id, (int)$service['gym_id'], $contact_id > 0 ? $contact_id : null, $customer_name, $customer_email, $scheduled_at, $status, $notes, $id]);
    } elseif ($appointmentsHasContactId) {
        $stmt = $pdo->prepare("UPDATE appointments SET service_id=?,contact_id=?,customer_name=?,customer_email=?,scheduled_at=?,status=?,notes=? WHERE id=?");
        $stmt->execute([$service_id, $contact_id > 0 ? $contact_id : null, $customer_name, $customer_email, $scheduled_at, $status, $notes, $id]);
    } elseif ($appointmentsHasGymId) {
        $stmt = $pdo->prepare("UPDATE appointments SET service_id=?,gym_id=?,customer_name=?,customer_email=?,scheduled_at=?,status=?,notes=? WHERE id=?");
        $stmt->execute([$service_id, (int)$service['gym_id'], $customer_name, $customer_email, $scheduled_at, $status, $notes, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE appointments SET service_id=?,customer_name=?,customer_email=?,scheduled_at=?,status=?,notes=? WHERE id=?");
        $stmt->execute([$service_id, $customer_name, $customer_email, $scheduled_at, $status, $notes, $id]);
    }
    $pdo->commit();
    echo json_encode(['success'=>true]);
    exit;
}

if ($method === 'DELETE') {
    $parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/'));
    $id = (int)end($parts);
    if (!$id) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'ID mancante']); exit; }
    $pdo->beginTransaction();
    $existingStmt = $pdo->prepare(
        "SELECT {$aptGymExpr} AS appointment_gym_id
         FROM appointments a LEFT JOIN services s ON s.id=a.service_id
         WHERE a.id=? LIMIT 1 FOR UPDATE"
    );
    $existingStmt->execute([$id]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        $pdo->rollBack();
        http_response_code(404); echo json_encode(['success'=>false,'message'=>'Prenotazione non trovata']); exit;
    }

    $appointmentGymId = (int)($existing['appointment_gym_id'] ?? 0);
    $sessionGymId = (int)($_SESSION['gym_id'] ?? 0);
    if (!$isAdmin && (!$isOperator || !$sessionGymId || $appointmentGymId !== $sessionGymId)) {
        $pdo->rollBack();
        http_response_code(403); echo json_encode(['success'=>false,'message'=>'Puoi eliminare solo le prenotazioni della tua attività']); exit;
    }

    $deleteStmt = $pdo->prepare("DELETE FROM appointments WHERE id=?");
    $deleteStmt->execute([$id]);
    $pdo->commit();
    echo json_encode(['success'=>true]);
    exit;
}

http_response_code(405);
echo json_encode(['success'=>false,'message'=>'Method not allowed']);
?>
