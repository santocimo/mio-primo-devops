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
if (empty($_POST['id'])) {
    echo json_encode(['ok' => false, 'error' => 'missing']);
    exit;
}

$pdo = getPDO();
$contactId = (int)$_POST['id'];
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

try {
    if ($use_gym && $current_gym_id) {
        $stmt = $pdo->prepare('DELETE FROM visitatori WHERE id = ? AND gym_id = ?');
        $stmt->execute([$contactId, $current_gym_id]);
    } else {
        $stmt = $pdo->prepare('DELETE FROM visitatori WHERE id = ?');
        $stmt->execute([$contactId]);
    }
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'db']);
}
