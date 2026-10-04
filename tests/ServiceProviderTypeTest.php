<?php

use App\Database\DatabaseManager;
use PHPUnit\Framework\TestCase;

class ServiceProviderTypeTest extends TestCase
{
    public function testNormalizeProviderType(): void
    {
        $this->assertSame('internal', DatabaseManager::normalizeProviderType(null));
        $this->assertSame('internal', DatabaseManager::normalizeProviderType('internal'));
        $this->assertSame('external', DatabaseManager::normalizeProviderType('EXTERNAL'));
        $this->assertSame('internal', DatabaseManager::normalizeProviderType('unknown'));
    }

    public function testCanAccessGymResource(): void
    {
        $this->assertTrue(DatabaseManager::canAccessGymResource(1, 1, false));
        $this->assertFalse(DatabaseManager::canAccessGymResource(1, 2, false));
        $this->assertTrue(DatabaseManager::canAccessGymResource(1, 2, true));
    }
}
