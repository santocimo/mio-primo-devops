<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/inc/security.php';
require_once __DIR__ . '/inc/validation.php';
header('Content-Type: application/json');
if (!isset($_SESSION['admin_logged']) || strtoupper($_SESSION['user_role']) !== 'ADMIN') { echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
if (empty($_POST['csrf']) || !verify_csrf($_POST['csrf'])) { echo json_encode(['ok'=>false,'error'=>'csrf']); exit; }
try {
    $pdo = getPDO();
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $category = trim($_POST['category'] ?? 'gym');
    if ($name === '' || $slug === '' || $category === '') { echo json_encode(['ok'=>false,'error'=>'missing']); exit; }
    if (!validate_gym_name($name)) { echo json_encode(['ok'=>false,'error'=>'invalid_name']); exit; }
    if (!validate_slug($slug)) { echo json_encode(['ok'=>false,'error'=>'invalid_slug']); exit; }
    if (!validate_gym_category($category)) { echo json_encode(['ok'=>false,'error'=>'invalid_category']); exit; }
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
        $u = $pdo->prepare('UPDATE gyms SET name = ?, slug = ?, category = ? WHERE id = ?');
        $u->execute([$name, $slug, $category, $id]);
        echo json_encode(['ok'=>true,'id'=>$id]);
    } else {
        $i = $pdo->prepare('INSERT INTO gyms (name, slug, category) VALUES (?,?,?)');
        $i->execute([$name, $slug, $category]);
        echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]);
    }
} catch (Exception $e) { echo json_encode(['ok'=>false,'error'=>'db']); }
