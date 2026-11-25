<?php

use App\Audit\AdminAuditLogger;
use PHPUnit\Framework\TestCase;

class AdminAuditLoggerTest extends TestCase
{
    /**
     * @dataProvider adminRequestActions
     */
    public function testClassifiesAdminRequestWithoutRecordingQueryContents(
        string $method,
        string $resource,
        string $script,
        array $query,
        array $form,
        string $expected
    ): void {
        self::assertSame(
            $expected,
            AdminAuditLogger::classifyAction($method, $resource, $script, $query, $form)
        );
    }

    public static function adminRequestActions(): array
    {
        return [
            'list read' => ['GET', 'contacts', 'contacts.php', ['q' => 'private search'], [], 'read'],
            'customer export' => ['GET', 'contacts', 'contacts.php', ['export' => 'csv'], [], 'export'],
            'legacy export' => ['GET', 'contacts', 'index.php', ['export_excel' => '1'], [], 'export'],
            'create' => ['POST', 'users', 'save_user.php', [], [], 'create'],
            'update' => ['POST', 'users', 'save_user.php', [], ['id' => '17'], 'update'],
            'delete' => ['GET', 'contacts', 'delete_contact.php', ['id' => '17'], [], 'delete'],
            'API update' => ['PUT', 'users', 'users.php', [], [], 'update'],
        ];
    }
}
