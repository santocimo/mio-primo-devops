<?php
session_start();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'error'=>'invalid_method']); exit; }
if (!isset($_POST['gym_id'])) { echo json_encode(['ok'=>false,'error'=>'missing_gym_id']); exit; }
$gid = (int)$_POST['gym_id'];
require_once __DIR__ . '/db.php';
try {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id FROM gyms WHERE id = ? LIMIT 1");
    $stmt->execute([$gid]);
    $row = $stmt->fetch();
    if (!$row) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
    $_SESSION['gym_id'] = $gid;
    echo json_encode(['ok'=>true,'gym_id'=>$gid]);
} catch (Exception $e) {
    echo json_encode(['ok'=>false,'error'=>'db_error']);
}
?>
