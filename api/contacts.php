<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../inc/security.php';
require_once __DIR__ . '/../inc/validation.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth/verify_token.php';

// Auth: sessione web o Bearer token app
if (!verify_bearer_token()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require_active_api_subscription();

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
// Admin può filtrare per sede specifica passando ?gym_id=X oppure usando la gym_id di sessione;
// operatore sempre filtrato al suo gym.
if ($use_gym && $isAdmin) {
    if (isset($_GET['gym_id']) && (int)$_GET['gym_id'] > 0) {
        $gym_id = (int)$_GET['gym_id'];
    } elseif (!empty($_SESSION['gym_id'])) {
        $gym_id = (int)$_SESSION['gym_id'];
    } else {
        $gym_id = null; // admin senza filtro = tutte le sedi
    }
} elseif ($use_gym) {
    $gym_id = (int)($_SESSION['gym_id'] ?? 0);
    if ($gym_id <= 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Gym context missing']);
        exit;
    }
} else {
    $gym_id = null;
}
App\Audit\AdminAuditLogger::recordApiRequest(
    $pdo,
    'contacts',
    $isAdmin,
    $gym_id !== null ? (int)$gym_id : null
);

function contact_validation_error(
    string $nome,
    string $cognome,
    ?string $cf,
    ?string $nascita,
    ?string $luogo,
    ?string $sesso
): ?string {
    if ($nome === '' || $cognome === '') {
        return 'Nome e cognome obbligatori';
    }
    if ($cf !== null && !validate_tax_code($cf)) {
        return 'Codice fiscale non valido';
    }
    if ($nascita !== null && !validate_birth_date($nascita)) {
        return 'Data di nascita non valida';
    }
    if ($sesso !== null && !in_array($sesso, ['M', 'F'], true)) {
        return 'Sesso non valido';
    }
    return null;
}

// ── GET ──────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        if (!$isAdmin) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            exit;
        }
        if ($use_gym && $gym_id === null) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Select an activity before exporting contacts']);
            exit;
        }

        $filename = 'registro_' . date('d-m-Y') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'NOME', 'COGNOME', 'CF', 'DATA NASCITA', 'COMUNE', 'INDIRIZZO', 'RECAPITO', 'SESSO']);

        if ($use_gym && $gym_id !== null) {
            $stmt = $pdo->prepare("SELECT id,nome,cognome,codice_fiscale,data_nascita,luogo_nascita,indirizzo,recapito,sesso FROM visitatori WHERE gym_id=? ORDER BY id DESC");
            $stmt->execute([$gym_id]);
        } else {
            $stmt = $pdo->query("SELECT id,nome,cognome,codice_fiscale,data_nascita,luogo_nascita,indirizzo,recapito,sesso FROM visitatori ORDER BY id DESC");
        }

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    header('Content-Type: application/json');

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
    $cf = strtoupper(trim($d['codice_fiscale'] ?? ''));
    $cf = $cf !== '' ? $cf : null;
    $nascita = trim($d['data_nascita'] ?? '');
    $nascita = $nascita !== '' ? $nascita : null;
    $luogo = mb_strtoupper(trim($d['luogo_nascita'] ?? ''));
    $luogo = $luogo !== '' ? $luogo : null;
    $indirizzo = mb_strtoupper(trim($d['indirizzo'] ?? ''));
    $indirizzo = $indirizzo !== '' ? $indirizzo : null;
    $recapito = trim($d['recapito'] ?? '');
    $recapito = $recapito !== '' ? $recapito : null;
    $sesso = strtoupper(trim($d['sesso'] ?? ''));
    $sesso = $sesso !== '' ? $sesso : null;

    $validationError = contact_validation_error($nome, $cognome, $cf, $nascita, $luogo, $sesso);
    if ($validationError !== null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $validationError]);
        exit;
    }

    // Admin deve specificare la sede scelta per evitare salvataggi nel tenant predefinito.
    $effective_gym_id = $gym_id;
    if ($isAdmin && $use_gym) {
        $requestedGymId = isset($d['gym_id']) ? (int)$d['gym_id'] : 0;
        if ($requestedGymId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Selezionare un’attività prima di aggiungere un iscritto']);
            exit;
        }
        $gymStmt = $pdo->prepare('SELECT id FROM gyms WHERE id = ? LIMIT 1');
        $gymStmt->execute([$requestedGymId]);
        if (!$gymStmt->fetchColumn()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Attività non valida']);
            exit;
        }
        $effective_gym_id = $requestedGymId;
    }
    if ($use_gym) {
        $stmt = $pdo->prepare("INSERT INTO visitatori (nome,cognome,codice_fiscale,data_nascita,luogo_nascita,indirizzo,recapito,sesso,gym_id) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$nome,$cognome,$cf,$nascita,$luogo,$indirizzo,$recapito,$sesso,$effective_gym_id ?? 1]);
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
    $cf = strtoupper(trim($d['codice_fiscale'] ?? ''));
    $cf = $cf !== '' ? $cf : null;
    $nascita = trim($d['data_nascita'] ?? '');
    $nascita = $nascita !== '' ? $nascita : null;
    $luogo = mb_strtoupper(trim($d['luogo_nascita'] ?? ''));
    $luogo = $luogo !== '' ? $luogo : null;
    $indirizzo = mb_strtoupper(trim($d['indirizzo'] ?? ''));
    $indirizzo = $indirizzo !== '' ? $indirizzo : null;
    $recapito = trim($d['recapito'] ?? '');
    $recapito = $recapito !== '' ? $recapito : null;
    $sesso = strtoupper(trim($d['sesso'] ?? ''));
    $sesso = $sesso !== '' ? $sesso : null;

    $validationError = contact_validation_error($nome, $cognome, $cf, $nascita, $luogo, $sesso);
    if ($validationError !== null) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>$validationError]);
        exit;
    }

    if ($use_gym && $gym_id !== null) {
        $existing = $pdo->prepare('SELECT id FROM visitatori WHERE id=? AND gym_id=? LIMIT 1');
        $existing->execute([$id, $gym_id]);
    } else {
        $existing = $pdo->prepare('SELECT id FROM visitatori WHERE id=? LIMIT 1');
        $existing->execute([$id]);
    }
    if (!$existing->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success'=>false,'message'=>'Contatto non trovato']);
        exit;
    }

    if ($use_gym && $gym_id !== null) {
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

    if ($use_gym && $gym_id !== null) {
        $stmt = $pdo->prepare("DELETE FROM visitatori WHERE id=? AND gym_id=?");
        $stmt->execute([$id, $gym_id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM visitatori WHERE id=?");
        $stmt->execute([$id]);
    }
    if ($stmt->rowCount() !== 1) {
        http_response_code(404);
        echo json_encode(['success'=>false,'message'=>'Contatto non trovato']);
        exit;
    }
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
