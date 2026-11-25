<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../inc/security.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth/verify_token.php';

if (!verify_bearer_token()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require_active_api_subscription();

$role = strtoupper((string)($_SESSION['user_role'] ?? ''));
if (strpos($role, 'ADMIN') === false && strpos($role, 'SUPER') === false) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$limit = filter_var($_GET['limit'] ?? 100, FILTER_VALIDATE_INT);
if ($limit === false || $limit < 1 || $limit > 200) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Limit must be between 1 and 200']);
    exit;
}

$stmt = getPDO()->prepare(
    'SELECT a.id, a.actor_user_id, u.username AS actor_username, a.actor_role,
            a.action, a.resource_type, a.resource_id, a.gym_id,
            a.response_status, a.created_at, a.completed_at
     FROM admin_audit_log a
     LEFT JOIN users u ON u.id = a.actor_user_id
     ORDER BY a.id DESC
     LIMIT ' . (int)$limit
);
$stmt->execute();
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
$toUtcIso = static function (?string $value): ?string {
    if ($value === null || $value === '') {
        return null;
    }

    $timestamp = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        $value,
        new DateTimeZone('UTC')
    );
    $errors = DateTimeImmutable::getLastErrors();
    if (
        $timestamp === false
        || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
    ) {
        throw new RuntimeException('Invalid audit timestamp returned by the database.');
    }

    return $timestamp->format('Y-m-d\TH:i:s\Z');
};

foreach ($entries as &$entry) {
    $entry['created_at'] = $toUtcIso($entry['created_at']);
    $entry['completed_at'] = $toUtcIso($entry['completed_at']);
}
unset($entry);

echo json_encode($entries);
