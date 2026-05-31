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

function cf_is_valid(string $cf): bool {
    $cf = strtoupper(trim($cf));
    if (!preg_match('/^[A-Z0-9]{16}$/', $cf)) {
        return false;
    }

    $oddMap = [
        '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9, '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
        'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9, 'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
        'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11, 'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
        'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
    ];
    $evenMap = [
        '0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9,
        'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7, 'I' => 8, 'J' => 9,
        'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15, 'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19,
        'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23, 'Y' => 24, 'Z' => 25,
    ];

    $sum = 0;
    for ($i = 0; $i < 15; $i++) {
        $ch = $cf[$i];
        $sum += (($i + 1) % 2 !== 0) ? ($oddMap[$ch] ?? -9999) : ($evenMap[$ch] ?? -9999);
    }

    $expectedControl = chr(65 + ($sum % 26));
    return $cf[15] === $expectedControl;
}

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
    if ($cf !== '' && !cf_is_valid($cf)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Codice fiscale non valido']);
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

    if ($cf !== '' && !cf_is_valid($cf)) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Codice fiscale non valido']);
        exit;
    }

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
