<?php
require_once __DIR__ . '/inc/security.php';
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged'])) {
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}
if (empty($_POST['csrf']) || !verify_csrf($_POST['csrf'])) {
    echo json_encode(['ok' => false, 'error' => 'csrf']);
    exit;
}

$pdo = getPDO();
$use_gym = false;
$current_gym_id = null;

try {
    $colCheck = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = DATABASE() AND table_name = 'visitatori' AND column_name = 'gym_id'");
    $colCheck->execute();
    $use_gym = (bool)$colCheck->fetchColumn();
} catch (Exception $e) {
    $use_gym = false;
}

if ($use_gym && isset($_SESSION['gym_id'])) {
    $current_gym_id = (int)$_SESSION['gym_id'];
}

$id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
$nome = trim($_POST['nome'] ?? '');
$cognome = trim($_POST['cognome'] ?? '');
$codice_fiscale = strtoupper(trim($_POST['codice_fiscale'] ?? ''));
$data_nascita = trim($_POST['data_nascita'] ?? '');
$luogo_nascita = trim($_POST['luogo_nascita'] ?? '');
$indirizzo = trim($_POST['indirizzo'] ?? '');
$recapito = trim($_POST['recapito'] ?? '');
$sesso = trim($_POST['sesso'] ?? '');

if ($nome === '' || $cognome === '' || $codice_fiscale === '' || $data_nascita === '' || $luogo_nascita === '' || $indirizzo === '' || $sesso === '') {
    echo json_encode(['ok' => false, 'error' => 'missing']);
    exit;
}

try {
    if ($id) {
        if ($use_gym && $current_gym_id) {
            $stmt = $pdo->prepare('UPDATE visitatori SET nome = ?, cognome = ?, codice_fiscale = ?, data_nascita = ?, luogo_nascita = ?, indirizzo = ?, recapito = ?, sesso = ? WHERE id = ? AND gym_id = ?');
            $stmt->execute([$nome, $cognome, $codice_fiscale, $data_nascita, $luogo_nascita, $indirizzo, $recapito, $sesso, $id, $current_gym_id]);
        } else {
            $stmt = $pdo->prepare('UPDATE visitatori SET nome = ?, cognome = ?, codice_fiscale = ?, data_nascita = ?, luogo_nascita = ?, indirizzo = ?, recapito = ?, sesso = ? WHERE id = ?');
            $stmt->execute([$nome, $cognome, $codice_fiscale, $data_nascita, $luogo_nascita, $indirizzo, $recapito, $sesso, $id]);
        }
    } else {
        if ($use_gym && $current_gym_id) {
            $stmt = $pdo->prepare('INSERT INTO visitatori (nome, cognome, codice_fiscale, data_nascita, luogo_nascita, indirizzo, recapito, sesso, gym_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$nome, $cognome, $codice_fiscale, $data_nascita, $luogo_nascita, $indirizzo, $recapito, $sesso, $current_gym_id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO visitatori (nome, cognome, codice_fiscale, data_nascita, luogo_nascita, indirizzo, recapito, sesso) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$nome, $cognome, $codice_fiscale, $data_nascita, $luogo_nascita, $indirizzo, $recapito, $sesso]);
        }
    }
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'db']);
}
