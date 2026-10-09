<?php

require_once __DIR__ . '/../inc/recurring_subscriptions.php';

use PHPUnit\Framework\TestCase;

class RecurringSubscriptionsTest extends TestCase
{
    private function pdoReturning($value): PDO
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn($value);
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        return $pdo;
    }

    public function testOnlyBillingOwnerMayManageSubscription(): void
    {
        $this->assertTrue(is_gym_billing_owner($this->pdoReturning('2'), 2, 1));
        $this->assertFalse(is_gym_billing_owner($this->pdoReturning('2'), 3, 1));
        $this->assertFalse(is_gym_billing_owner($this->pdoReturning(false), 2, 1));
        $this->assertFalse(is_gym_billing_owner($this->pdoReturning('2'), 0, 1));
    }

    public function testSaveRejectsUnknownPlanStatusAndProvider(): void
    {
        $pdo = $this->createMock(PDO::class);
        foreach ([
            ['stripe', 'sub_1', 'nope', 'active'],
            ['stripe', 'sub_1', 'monthly', 'bogus'],
            ['other', 'sub_1', 'monthly', 'active'],
            ['stripe', '', 'monthly', 'active'],
        ] as [$provider, $id, $plan, $status]) {
            try {
                save_gym_recurring_subscription($pdo, 1, $provider, $id, $plan, $status, null);
                $this->fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testSaveRefusesSubscriptionLinkedToAnotherGym(): void
    {
        $this->expectException(RuntimeException::class);
        save_gym_recurring_subscription($this->pdoReturning('99'), 1, 'stripe', 'sub_1', 'monthly', 'active', null);
    }

    public function testPaymentMustMatchConfiguredPlanAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        record_gym_subscription_payment($this->createMock(PDO::class), 'stripe', 'in_1', 1, 'monthly', 1, 'EUR');
    }
}
