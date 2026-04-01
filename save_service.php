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
    $gym_id = !empty($_POST['gym_id']) ? (int)$_POST['gym_id'] : null;
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $category = trim($_POST['category'] ?? 'class');
    $description = trim($_POST['description'] ?? '');
    $duration = trim($_POST['duration_minutes'] ?? '60');
    $capacity = trim($_POST['capacity'] ?? '10');
    $price = trim($_POST['price'] ?? '');

    if (!$gym_id || $name === '' || $slug === '') { echo json_encode(['ok'=>false,'error'=>'missing']); exit; }
    if (!validate_service_name($name)) { echo json_encode(['ok'=>false,'error'=>'invalid_name']); exit; }
    if (!validate_slug($slug)) { echo json_encode(['ok'=>false,'error'=>'invalid_slug']); exit; }
    if (!validate_service_category($category)) { echo json_encode(['ok'=>false,'error'=>'invalid_category']); exit; }
    if (!validate_duration_minutes($duration)) { echo json_encode(['ok'=>false,'error'=>'invalid_duration']); exit; }
    if (!validate_capacity($capacity)) { echo json_encode(['ok'=>false,'error'=>'invalid_capacity']); exit; }
    if ($price !== '' && !validate_price($price)) { echo json_encode(['ok'=>false,'error'=>'invalid_price']); exit; }

    $priceValue = $price === '' ? null : number_format((float)str_replace(',', '.', $price), 2, '.', '');

    if ($id) {
        $stmt = $pdo->prepare('SELECT id FROM services WHERE gym_id = ? AND slug = ? AND id != ? LIMIT 1');
        $stmt->execute([$gym_id, $slug, $id]);
    } else {
        $stmt = $pdo->prepare('SELECT id FROM services WHERE gym_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$gym_id, $slug]);
    }
    if ($stmt->fetch()) { echo json_encode(['ok'=>false,'error'=>'slug_taken']); exit; }

    if ($id) {
        $u = $pdo->prepare('UPDATE services SET gym_id = ?, name = ?, slug = ?, category = ?, description = ?, duration_minutes = ?, capacity = ?, price = ? WHERE id = ?');
        $u->execute([$gym_id, $name, $slug, $category, $description ?: null, (int)$duration, (int)$capacity, $priceValue, $id]);
        echo json_encode(['ok'=>true,'id'=>$id]);
    } else {
        $i = $pdo->prepare('INSERT INTO services (gym_id, name, slug, category, description, duration_minutes, capacity, price) VALUES (?,?,?,?,?,?,?,?)');
        $i->execute([$gym_id, $name, $slug, $category, $description ?: null, (int)$duration, (int)$capacity, $priceValue]);
        echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]);
    }
} catch (Exception $e) {
    echo json_encode(['ok'=>false,'error'=>'db']);
}
