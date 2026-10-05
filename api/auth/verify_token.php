<?php
/**
 * Validates mobile bearer tokens and refreshes authorization from the database.
 */
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../inc/security.php';
require_once __DIR__ . '/../../inc/api_token.php';
require_once __DIR__ . '/../../inc/subscription.php';

function set_authenticated_user(array $user): bool {
    $role = strtoupper((string)$user['role']);
    $subscription = compute_subscription($user);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = (string)$user['username'];
    $_SESSION['user_role'] = $role;
    $_SESSION['admin_logged'] = str_contains($role, 'ADMIN') || str_contains($role, 'SUPER');
    $_SESSION['gym_id'] = isset($user['gym_id']) ? (int)$user['gym_id'] : null;
    $_SESSION['subscription_status'] = $subscription['status'];
    $_SESSION['trial_start_date'] = $user['trial_start_date'] ?? null;
    $_SESSION['subscription_expires_at'] = $user['subscription_expires_at'] ?? null;
    $_SESSION['authenticated_user'] = [
        'id' => (int)$user['id'],
        'name' => (string)($user['name'] ?? ''),
        'email' => (string)($user['email'] ?? ''),
        'username' => (string)$user['username'],
        'role' => strtolower($role),
        'gym_id' => isset($user['gym_id']) ? (int)$user['gym_id'] : null,
    ];
    $_SESSION['authenticated_subscription'] = $subscription;
    return true;
}

function require_active_api_subscription(): void {
    $status = $_SESSION['authenticated_subscription']['status'] ?? 'expired';
    if ($status !== 'active' && $status !== 'trial') {
        http_response_code(402);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Subscription required', 'subscription_status' => $status]);
        exit;
    }
}

function verify_bearer_token(): bool {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($auth === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    try {
        if (!preg_match('/^Bearer\s+(\S+)$/i', (string)$auth, $matches)) {
            if (empty($_SESSION['admin_logged']) || (int)($_SESSION['user_id'] ?? 0) <= 0) {
                return false;
            }

            $stmt = getPDO()->prepare(
                'SELECT id, name, email, username, role, gym_id, trial_start_date, subscription_status, subscription_plan, subscription_expires_at
                 FROM users WHERE id = ? LIMIT 1'
            );
            $stmt->execute([(int)$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || (isset($_SESSION['username']) && !hash_equals((string)$user['username'], (string)$_SESSION['username']))) {
                return false;
            }
            return set_authenticated_user($user);
        }

        $payload = decode_api_token($matches[1]);
        if ($payload === null) {
            return false;
        }

        $userId = $payload['user_id'];
        if ($userId <= 0) {
            return false;
        }

        $stmt = getPDO()->prepare(
            'SELECT id, name, email, username, role, gym_id, trial_start_date, subscription_status, subscription_plan, subscription_expires_at
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user
            || !hash_equals((string)$user['username'], $payload['username'])
            || strtoupper((string)$user['role']) !== $payload['role']
            || (isset($payload['gym_id']) ? (int)$payload['gym_id'] : null) !== (isset($user['gym_id']) ? (int)$user['gym_id'] : null)) {
            return false;
        }

        return set_authenticated_user($user);
    } catch (RuntimeException $e) {
        error_log('API token configuration error: ' . $e->getMessage());
        return false;
    } catch (PDOException $e) {
        error_log('API token user lookup failed: ' . $e->getMessage());
        return false;
    }
}

// Direct endpoint used by the mobile app when restoring a saved bearer token.
$isDirectRequest = isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__;
if (PHP_SAPI !== 'cli' && $isDirectRequest) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    if (verify_bearer_token()) {
        echo json_encode([
            'success' => true,
            'user' => $_SESSION['authenticated_user'],
            'subscription' => $_SESSION['authenticated_subscription'],
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    }
    exit;
}
