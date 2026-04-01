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
    $service_id = !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null;
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    $scheduled_at = trim($_POST['scheduled_at'] ?? '');
    $status = trim($_POST['status'] ?? 'pending');
    $notes = trim($_POST['notes'] ?? '');

    if (!$service_id || $customer_name === '' || $scheduled_at === '') { echo json_encode(['ok'=>false,'error'=>'missing']); exit; }
    if (!validate_status($status)) { echo json_encode(['ok'=>false,'error'=>'invalid_status']); exit; }
    if ($customer_email !== '' && !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['ok'=>false,'error'=>'invalid_email']); exit; }

    $scheduled = DateTime::createFromFormat('Y-m-d\TH:i', $scheduled_at);
    if (!$scheduled) {
        $scheduled = DateTime::createFromFormat('Y-m-d H:i:s', $scheduled_at);
    }
    if (!$scheduled) { echo json_encode(['ok'=>false,'error'=>'invalid_datetime']); exit; }
    $scheduledValue = $scheduled->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare('SELECT id FROM services WHERE id = ? LIMIT 1');
    $stmt->execute([$service_id]);
    if (!$stmt->fetch()) { echo json_encode(['ok'=>false,'error'=>'invalid_service']); exit; }

    if ($id) {
        $u = $pdo->prepare('UPDATE appointments SET service_id = ?, customer_name = ?, customer_email = ?, scheduled_at = ?, status = ?, notes = ? WHERE id = ?');
        $u->execute([$service_id, $customer_name, $customer_email ?: null, $scheduledValue, $status, $notes ?: null, $id]);
        echo json_encode(['ok'=>true,'id'=>$id]);
    } else {
        $i = $pdo->prepare('INSERT INTO appointments (service_id, customer_name, customer_email, scheduled_at, status, notes) VALUES (?,?,?,?,?,?)');
        $i->execute([$service_id, $customer_name, $customer_email ?: null, $scheduledValue, $status, $notes ?: null]);
        echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]);
    }
} catch (Exception $e) {
    echo json_encode(['ok'=>false,'error'=>'db']);
}
