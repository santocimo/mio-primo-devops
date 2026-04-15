<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../inc/security.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth/verify_token.php';

// Auth: sessione web o Bearer token app
if (!isset($_SESSION['admin_logged'])) {
    if (!verify_bearer_token()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

$pdo = getPDO();
$method = $_SERVER['REQUEST_METHOD'];

// Determina se admin (vede tutti i contatti di tutte le sedi)
$userRole = strtoupper($_SESSION['user_role'] ?? '');
$isAdmin = strpos($userRole, 'ADMIN') !== false || strpos($userRole, 'SUPER') !== false;

// Determina gym_id del tenant corrente
$use_gym = false;
try {
    $col = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema=DATABASE() AND table_name='visitatori' AND column_name='gym_id'");
    $col->execute();
    $use_gym = (bool)$col->fetchColumn();
} catch (Exception $e) {}
// Admin vede tutto (gym_id = null = senza filtro), operatore filtrato
$gym_id = ($use_gym && !$isAdmin) ? (int)($_SESSION['gym_id'] ?? 1) : null;

// ── GET ──────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    // Statistiche: ?stats=1
    if (isset($_GET['stats'])) {
        if ($use_gym && $gym_id !== null) {
            $tot = $pdo->prepare("SELECT COUNT(*) FROM visitatori WHERE gym_id=?"); $tot->execute([$gym_id]);
            $men = $pdo->prepare("SELECT COUNT(*) FROM visitatori WHERE sesso='M' AND gym_id=?"); $men->execute([$gym_id]);
            $wom = $pdo->prepare("SELECT COUNT(*) FROM visitatori WHERE sesso='F' AND gym_id=?"); $wom->execute([$gym_id]);
        } else {
            $tot = $pdo->query("SELECT COUNT(*) FROM visitatori");
            $men = $pdo->query("SELECT COUNT(*) FROM visitatori WHERE sesso='M'");
            $wom = $pdo->query("SELECT COUNT(*) FROM visitatori WHERE sesso='F'");
        }
        echo json_encode(['total' => (int)$tot->fetchColumn(), 'men' => (int)$men->fetchColumn(), 'women' => (int)$wom->fetchColumn()]);
        exit;
    }

    // Ricerca / lista: ?q=...
    $q = trim($_GET['q'] ?? '');
    if ($q !== '') {
        $like = '%' . $q . '%';
        if ($use_gym && $gym_id !== null) {
            $stmt = $pdo->prepare("SELECT id,nome,cognome,codice_fiscale,data_nascita,luogo_nascita,indirizzo,recapito,sesso FROM visitatori WHERE gym_id=? AND CONCAT_WS(' ',nome,cognome,codice_fiscale,luogo_nascita,indirizzo,recapito) LIKE ? ORDER BY id DESC LIMIT 100");
            $stmt->execute([$gym_id, $like]);
        } else {
            $stmt = $pdo->prepare("SELECT id,nome,cognome,codice_fiscale,data_nascita,luogo_nascita,indirizzo,recapito,sesso FROM visitatori WHERE CONCAT_WS(' ',nome,cognome,codice_fiscale,luogo_nascita,indirizzo,recapito) LIKE ? ORDER BY id DESC LIMIT 100");
            $stmt->execute([$like]);
        }
    } else {
        if ($use_gym && $gym_id !== null) {
            $stmt = $pdo->prepare("SELECT id,nome,cognome,codice_fiscale,data_nascita,luogo_nascita,indirizzo,recapito,sesso FROM visitatori WHERE gym_id=? ORDER BY id DESC LIMIT 200");
            $stmt->execute([$gym_id]);
        } else {
            $stmt = $pdo->query("SELECT id,nome,cognome,codice_fiscale,data_nascita,luogo_nascita,indirizzo,recapito,sesso FROM visitatori ORDER BY id DESC LIMIT 200");
        }
    }
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ── POST (crea) ──────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true);
    $nome     = mb_strtoupper(trim($d['nome'] ?? ''));
    $cognome  = mb_strtoupper(trim($d['cognome'] ?? ''));
    $cf       = strtoupper(trim($d['codice_fiscale'] ?? ''));
    $nascita  = $d['data_nascita'] ?? '';
    $luogo    = mb_strtoupper(trim($d['luogo_nascita'] ?? ''));
    $indirizzo = mb_strtoupper(trim($d['indirizzo'] ?? ''));
    $recapito = trim($d['recapito'] ?? '');
    $sesso    = $d['sesso'] ?? 'M';

    if (!$nome || !$cognome) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nome e cognome obbligatori']);
        exit;
    }

    if ($use_gym) {
        $stmt = $pdo->prepare("INSERT INTO visitatori (nome,cognome,codice_fiscale,data_nascita,luogo_nascita,indirizzo,recapito,sesso,gym_id) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$nome,$cognome,$cf,$nascita,$luogo,$indirizzo,$recapito,$sesso,$gym_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO visitatori (nome,cognome,codice_fiscale,data_nascita,luogo_nascita,indirizzo,recapito,sesso) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$nome,$cognome,$cf,$nascita,$luogo,$indirizzo,$recapito,$sesso]);
    }
    echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
    exit;
}

// ── PUT (modifica) ────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    $parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
    $id = (int)end($parts);
    if (!$id) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'ID mancante']); exit; }

    $d = json_decode(file_get_contents('php://input'), true);
    $nome      = mb_strtoupper(trim($d['nome'] ?? ''));
    $cognome   = mb_strtoupper(trim($d['cognome'] ?? ''));
    $cf        = strtoupper(trim($d['codice_fiscale'] ?? ''));
    $nascita   = $d['data_nascita'] ?? '';
    $luogo     = mb_strtoupper(trim($d['luogo_nascita'] ?? ''));
    $indirizzo = mb_strtoupper(trim($d['indirizzo'] ?? ''));
    $recapito  = trim($d['recapito'] ?? '');
    $sesso     = $d['sesso'] ?? 'M';

    if ($use_gym) {
        $stmt = $pdo->prepare("UPDATE visitatori SET nome=?,cognome=?,codice_fiscale=?,data_nascita=?,luogo_nascita=?,indirizzo=?,recapito=?,sesso=? WHERE id=? AND gym_id=?");
        $stmt->execute([$nome,$cognome,$cf,$nascita,$luogo,$indirizzo,$recapito,$sesso,$id,$gym_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE visitatori SET nome=?,cognome=?,codice_fiscale=?,data_nascita=?,luogo_nascita=?,indirizzo=?,recapito=?,sesso=? WHERE id=?");
        $stmt->execute([$nome,$cognome,$cf,$nascita,$luogo,$indirizzo,$recapito,$sesso,$id]);
    }
    echo json_encode(['success' => true]);
    exit;
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
    $id = (int)end($parts);
    if (!$id) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'ID mancante']); exit; }

    if ($use_gym) {
        $stmt = $pdo->prepare("DELETE FROM visitatori WHERE id=? AND gym_id=?");
        $stmt->execute([$id, $gym_id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM visitatori WHERE id=?");
        $stmt->execute([$id]);
    }
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
