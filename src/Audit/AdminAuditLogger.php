<?php

namespace App\Audit;

use App\Database\DatabaseManager;
use PDO;
use PDOException;
use RuntimeException;

final class AdminAuditLogger
{
    public const RETENTION_DAYS = 90;

    private const API_RESOURCES = [
        'users',
        'gyms',
        'contacts',
        'appointments',
        'services',
        'settings',
    ];

    private const LEGACY_SCRIPTS = [
        'index.php' => 'contacts',
        'contacts.php' => 'contacts',
        'save_contact.php' => 'contacts',
        'delete_contact.php' => 'contacts',
        'appointments.php' => 'appointments',
        'save_appointment.php' => 'appointments',
        'delete_appointment.php' => 'appointments',
        'services.php' => 'services',
        'save_service.php' => 'services',
        'delete_service.php' => 'services',
        'gyms.php' => 'gyms',
        'save_gym.php' => 'gyms',
        'delete_gym.php' => 'gyms',
        'users.php' => 'users',
        'save_user.php' => 'users',
        'delete_user.php' => 'users',
        'settings.php' => 'settings',
        'set_gym.php' => 'gyms',
    ];

    public static function purgeExpired(PDO $pdo): int
    {
        $deleted = $pdo->exec(
            'DELETE FROM admin_audit_log
             WHERE created_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL ' . self::RETENTION_DAYS . ' DAY)'
        );

        if ($deleted === false) {
            throw new RuntimeException('Could not purge expired administrator audit records');
        }

        return $deleted;
    }

    public static function recordApiRequest(PDO $pdo, string $resource, bool $isAdmin, ?int $gymId = null): void
    {
        if (!$isAdmin || !in_array($resource, self::API_RESOURCES, true)) {
            return;
        }

        self::record($pdo, $resource, $gymId, basename((string)($_SERVER['SCRIPT_NAME'] ?? '')));
    }

    public static function recordLegacyRequest(): void
    {
        if (PHP_SAPI === 'cli' || self::isApiRequest() || !self::isAdmin()) {
            return;
        }

        $script = basename((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
        if (!isset(self::LEGACY_SCRIPTS[$script])) {
            return;
        }

        self::record(
            DatabaseManager::getInstance()->getConnection(),
            self::LEGACY_SCRIPTS[$script],
            null,
            $script
        );
    }

    public static function classifyAction(
        string $method,
        string $resource,
        string $script,
        array $query,
        array $form = []
    ): string
    {
        $method = strtoupper($method);

        if (strpos($script, 'delete_') === 0) {
            return 'delete';
        }

        if ($script === 'set_gym.php') {
            return 'update';
        }

        if ($method === 'GET') {
            if (($resource === 'contacts' && ($query['export'] ?? '') === 'csv')
                || ($script === 'index.php' && isset($query['export_excel']))) {
                return 'export';
            }
            return 'read';
        }

        if ($method === 'POST' && strpos($script, 'save_') === 0) {
            return !empty($form['id']) ? 'update' : 'create';
        }

        if ($method === 'POST') {
            return 'create';
        }

        if ($method === 'PUT') {
            return 'update';
        }

        if ($method === 'DELETE') {
            return 'delete';
        }

        if ($method === 'PATCH') {
            return 'update';
        }

        return 'other';
    }

    private static function record(PDO $pdo, string $resource, ?int $gymId, string $script): void
    {
        $actorId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
        $role = strtoupper((string)($_SESSION['user_role'] ?? ''));
        if (!$actorId || !self::isAdmin()) {
            throw new RuntimeException('Cannot audit an unauthenticated administrator request');
        }

        $gymId = self::resolveId($_GET['gym_id'] ?? $_POST['gym_id'] ?? $gymId ?? $_SESSION['gym_id'] ?? null);
        $resourceId = self::resolveId(
            $_GET['id'] ?? $_POST['id'] ?? self::resourceIdFromPath((string)($_SERVER['REQUEST_URI'] ?? ''))
        );

        $stmt = $pdo->prepare(
            'INSERT INTO admin_audit_log
                (actor_user_id, actor_role, action, resource_type, resource_id, gym_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int)$actorId,
            $role,
            self::classifyAction(
                (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
                $resource,
                $script,
                $_GET,
                $_POST
            ),
            $resource,
            $resourceId,
            $gymId,
        ]);
        $auditId = (int)$pdo->lastInsertId();

        register_shutdown_function(static function () use ($pdo, $auditId): void {
            try {
                $status = http_response_code();
                $update = $pdo->prepare(
                    'UPDATE admin_audit_log
                     SET response_status = ?, completed_at = CURRENT_TIMESTAMP
                     WHERE id = ?'
                );
                $update->execute([$status !== false ? $status : 200, $auditId]);
            } catch (PDOException $e) {
                error_log('Could not finalize administrator audit event: ' . $e->getMessage());
            }
        });
    }

    private static function isAdmin(): bool
    {
        $role = strtoupper((string)($_SESSION['user_role'] ?? ''));
        return strpos($role, 'ADMIN') !== false || strpos($role, 'SUPER') !== false;
    }

    private static function isApiRequest(): bool
    {
        $path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
        return strpos($path, '/api/') === 0;
    }

    private static function resolveId($value): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        return $id !== false && $id > 0 ? (int)$id : null;
    }

    private static function resourceIdFromPath(string $uri): ?int
    {
        $path = (string)(parse_url($uri, PHP_URL_PATH) ?? '');
        return preg_match('~/(?:users|gyms|contacts|appointments|services)/([1-9][0-9]*)$~', $path, $matches)
            ? self::resolveId($matches[1])
            : null;
    }
}
