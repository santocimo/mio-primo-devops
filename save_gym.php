<?php
require_once __DIR__ . '/db.php';
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['admin_logged']) || strtoupper($_SESSION['user_role']) !== 'ADMIN') { echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
try {
    $pdo = getPDO();
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    if ($name === '' || $slug === '') { echo json_encode(['ok'=>false,'error'=>'missing']); exit; }
    // ensure slug unique
    if ($id) {
        $stmt = $pdo->prepare('SELECT id FROM gyms WHERE slug = ? AND id != ? LIMIT 1');
        $stmt->execute([$slug, $id]);
    } else {
        $stmt = $pdo->prepare('SELECT id FROM gyms WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
    }
    if ($stmt->fetch()) { echo json_encode(['ok'=>false,'error'=>'slug_taken']); exit; }
    if ($id) {
        $u = $pdo->prepare('UPDATE gyms SET name = ?, slug = ? WHERE id = ?');
        $u->execute([$name, $slug, $id]);
        echo json_encode(['ok'=>true,'id'=>$id]);
    } else {
        $i = $pdo->prepare('INSERT INTO gyms (name, slug) VALUES (?,?)');
        $i->execute([$name, $slug]);
        echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]);
    }
} catch (Exception $e) { echo json_encode(['ok'=>false,'error'=>'db']); }
