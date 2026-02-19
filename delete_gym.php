<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/inc/security.php';
header('Content-Type: application/json');
if (!isset($_SESSION['admin_logged']) || strtoupper($_SESSION['user_role']) !== 'ADMIN') { echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
if (empty($_POST['csrf']) || !verify_csrf($_POST['csrf'])) { echo json_encode(['ok'=>false,'error'=>'csrf']); exit; }
try {
    $pdo = getPDO();
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) { echo json_encode(['ok'=>false,'error'=>'missing']); exit; }
    // prevent deleting the gym if visitatori rows exist
    $c = $pdo->prepare('SELECT COUNT(*) FROM visitatori WHERE gym_id = ?');
    $c->execute([$id]);
    if ($c->fetchColumn() > 0) { echo json_encode(['ok'=>false,'error'=>'in_use']); exit; }
    $d = $pdo->prepare('DELETE FROM gyms WHERE id = ?');
    $d->execute([$id]);
    echo json_encode(['ok'=>true]);
} catch (Exception $e) { echo json_encode(['ok'=>false,'error'=>'db']); }
