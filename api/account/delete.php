<?php
require_once __DIR__ . '/../../inc/security.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth/verify_token.php';
require_once __DIR__ . '/../../inc/recurring_subscriptions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

function account_deletion_fail(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    account_deletion_fail(405, 'Method not allowed');
}
if (!verify_bearer_token()) {
    account_deletion_fail(401, 'Unauthorized');
}

$input = json_decode(file_get_contents('php://input'), true);
$password = is_array($input) ? (string)($input['password'] ?? '') : '';
$confirmation = is_array($input) ? trim((string)($input['confirmation'] ?? '')) : '';
if ($password === '' || !in_array($confirmation, ['ELIMINA', 'DELETE'], true)) {
    account_deletion_fail(400, 'Re-enter your password and confirm the deletion');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    account_deletion_fail(401, 'Unauthorized');
}

try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    $userQuery = $pdo->prepare('SELECT id, gym_id, role, password_hash FROM users WHERE id = ? FOR UPDATE');
    $userQuery->execute([$userId]);
    $user = $userQuery->fetch(PDO::FETCH_ASSOC);
    if (!$user || !password_verify($password, (string)$user['password_hash'])) {
        $pdo->rollBack();
        account_deletion_fail(403, 'Password verification failed');
    }

    $role = strtoupper((string)$user['role']);
    $isGlobalAdmin = str_contains($role, 'ADMIN') || str_contains($role, 'SUPER');
    $gymId = isset($user['gym_id']) ? (int)$user['gym_id'] : 0;
    $ownerQuery = $gymId > 0
        ? $pdo->prepare('SELECT billing_owner_user_id FROM gyms WHERE id = ? LIMIT 1')
        : null;
    if ($ownerQuery) {
        $ownerQuery->execute([$gymId]);
    }
    $isBillingOwner = $ownerQuery && (int)$ownerQuery->fetchColumn() === $userId;
    $deletesActivity = $gymId > 0 && !$isGlobalAdmin && $isBillingOwner;

    if ($deletesActivity) {
        $gymQuery = $pdo->prepare('SELECT slug FROM gyms WHERE id = ? FOR UPDATE');
        $gymQuery->execute([$gymId]);
        $gym = $gymQuery->fetch(PDO::FETCH_ASSOC);
        if (!$gym) {
            $pdo->rollBack();
            account_deletion_fail(409, 'The activity could not be identified safely');
        }
        if (($gym['slug'] ?? '') === 'default') {
            $pdo->rollBack();
            account_deletion_fail(409, 'The shared default activity cannot be deleted from an account');
        }
        if (gym_has_current_paid_subscription($pdo, $gymId)) {
            $pdo->rollBack();
            account_deletion_fail(
                409,
                'Cancel the gym subscription and wait until the paid period ends before deleting this account'
            );
        }
        $otherUsers = $pdo->prepare(
            'SELECT id FROM users WHERE gym_id = ? AND id <> ? LIMIT 1 FOR UPDATE'
        );
        $otherUsers->execute([$gymId, $userId]);
        if ($otherUsers->fetchColumn()) {
            $pdo->rollBack();
            account_deletion_fail(
                409,
                'Transfer billing ownership to another gym account before deleting this account'
            );
        }

        $preservePayments = $pdo->prepare(
            'UPDATE subscription_payments SET user_id = NULL, gym_id = NULL
             WHERE user_id IN (SELECT id FROM users WHERE gym_id = ?)'
        );
        $preservePayments->execute([$gymId]);
        $preserveGymPayments = $pdo->prepare(
            'UPDATE subscription_payments SET gym_id = NULL WHERE gym_id = ?'
        );
        $preserveGymPayments->execute([$gymId]);

        $deleteContacts = $pdo->prepare('DELETE FROM visitatori WHERE gym_id = ?');
        $deleteContacts->execute([$gymId]);

        $deleteUsers = $pdo->prepare('DELETE FROM users WHERE gym_id = ?');
        $deleteUsers->execute([$gymId]);

        $deleteGym = $pdo->prepare('DELETE FROM gyms WHERE id = ?');
        $deleteGym->execute([$gymId]);
        if ($deleteGym->rowCount() !== 1) {
            throw new RuntimeException('Activity deletion did not remove exactly one activity');
        }
    } else {
        $preservePayments = $pdo->prepare('UPDATE subscription_payments SET user_id = NULL WHERE user_id = ?');
        $preservePayments->execute([$userId]);

        $deleteUser = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $deleteUser->execute([$userId]);
        if ($deleteUser->rowCount() !== 1) {
            throw new RuntimeException('Account deletion did not remove exactly one account');
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'activity_deleted' => $deletesActivity]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Account deletion failed: ' . $e->getMessage());
    account_deletion_fail(500, 'Could not complete account deletion');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Account deletion failed: ' . $e->getMessage());
    account_deletion_fail(500, 'Could not complete account deletion');
}
