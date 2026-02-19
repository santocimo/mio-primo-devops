<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/inc/security.php';
header('Content-Type: application/json');
if (!isset($_SESSION['admin_logged']) || strtoupper($_SESSION['user_role']) !== 'ADMIN' || isset($_SESSION['gym_id'])) { echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
if (empty($_POST['csrf']) || !verify_csrf($_POST['csrf'])) { echo json_encode(['ok'=>false,'error'=>'csrf']); exit; }
try {
    $pdo = getPDO();
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) { echo json_encode(['ok'=>false,'error'=>'missing']); exit; }
    $d = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $d->execute([$id]);
    echo json_encode(['ok'=>true]);
} catch (Exception $e) { echo json_encode(['ok'=>false,'error'=>'db']); }
