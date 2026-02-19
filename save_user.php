<?php
require_once __DIR__ . '/db.php';
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['admin_logged']) || strtoupper($_SESSION['user_role']) !== 'ADMIN' || isset($_SESSION['gym_id'])) { echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
try {
    $pdo = getPDO();
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? 'OPERATORE');
    $gym_id = isset($_POST['gym_id']) && $_POST['gym_id'] !== '' ? (int)$_POST['gym_id'] : null;
    if ($username === '') { echo json_encode(['ok'=>false,'error'=>'missing']); exit; }
    // ensure username unique
    if ($id) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1');
        $stmt->execute([$username, $id]);
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
    }
    if ($stmt->fetch()) { echo json_encode(['ok'=>false,'error'=>'username_taken']); exit; }
    if ($id) {
        if ($password !== '') {
            $ph = password_hash($password, PASSWORD_DEFAULT);
            $u = $pdo->prepare('UPDATE users SET username = ?, password_hash = ?, role = ?, gym_id = ? WHERE id = ?');
            $u->execute([$username, $ph, $role, $gym_id, $id]);
        } else {
            $u = $pdo->prepare('UPDATE users SET username = ?, role = ?, gym_id = ? WHERE id = ?');
            $u->execute([$username, $role, $gym_id, $id]);
        }
        echo json_encode(['ok'=>true,'id'=>$id]);
    } else {
        if ($password === '') { echo json_encode(['ok'=>false,'error'=>'missing_password']); exit; }
        $ph = password_hash($password, PASSWORD_DEFAULT);
        $i = $pdo->prepare('INSERT INTO users (username, password_hash, role, gym_id) VALUES (?,?,?,?)');
        $i->execute([$username, $ph, $role, $gym_id]);
        echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]);
    }
} catch (Exception $e) { echo json_encode(['ok'=>false,'error'=>'db']); }
