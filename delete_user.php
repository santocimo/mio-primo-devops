<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/inc/security.php';
header('Content-Type: application/json');
$role = isset($_SESSION['user_role']) ? trim(strtoupper($_SESSION['user_role'])) : '';
$is_global_admin = isset($_SESSION['admin_logged']) && $role !== '' && (strpos($role, 'ADMIN') !== false || strpos($role, 'SUPER') !== false);
if (!$is_global_admin) { echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
if (empty($_POST['csrf']) || !verify_csrf($_POST['csrf'])) { echo json_encode(['ok'=>false,'error'=>'csrf']); exit; }
try {
    $pdo = getPDO();
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) { echo json_encode(['ok'=>false,'error'=>'missing']); exit; }
    $d = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $d->execute([$id]);
    echo json_encode(['ok'=>true]);
} catch (Exception $e) { echo json_encode(['ok'=>false,'error'=>'db']); }
