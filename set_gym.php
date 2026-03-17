<?php
require_once __DIR__ . '/inc/security.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'error'=>'invalid_method']); exit; }
if (!isset($_SESSION['admin_logged']) || !isset($_SESSION['user_role']) || !in_array(strtoupper($_SESSION['user_role']), ['ADMIN', 'SUPER'], true)) { echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
if (empty($_POST['csrf']) || !verify_csrf($_POST['csrf'])) { echo json_encode(['ok'=>false,'error'=>'csrf']); exit; }
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
    // Return a machine-friendly error and a debug message for easier diagnosis
    $resp = ['ok'=>false,'error'=>'db_error'];
    if (!empty($e->getMessage())) {
        $resp['detail'] = $e->getMessage();
    }
    echo json_encode($resp);
}
?>
