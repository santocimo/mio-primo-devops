<?php

require_once __DIR__ . '/../inc/subscription.php';

use PHPUnit\Framework\TestCase;

class GymSubscriptionTest extends TestCase
{
    public function testSeparateBillingContactAndOperatorsShareGymSubscription(): void
    {
        $sharedSubscription = [
            'trial_start_date' => null,
            'status' => 'active',
            'plan' => 'monthly',
            'current_period_end' => date('Y-m-d H:i:s', time() + 86400),
            'provider' => 'stripe',
            'cancel_at_period_end' => 0,
        ];
        $billingContact = compute_gym_subscription_state(
            ['role' => 'GESTORE'],
            $sharedSubscription
        );
        $operator = compute_gym_subscription_state(
            ['role' => 'operatore'],
            $sharedSubscription
        );

        self::assertSame('active', $billingContact['status']);
        self::assertSame($billingContact['status'], $operator['status']);
        self::assertSame($billingContact['plan'], $operator['plan']);
        self::assertTrue($billingContact['auto_renew']);
        self::assertTrue($operator['auto_renew']);
    }

    public function testSingleOperatorCanOwnGymSubscription(): void
    {
        $subscription = compute_gym_subscription_state(
            ['role' => 'operatore'],
            [
                'trial_start_date' => null,
                'status' => 'active',
                'plan' => 'monthly',
                'current_period_end' => date('Y-m-d H:i:s', time() + 86400),
                'provider' => 'paypal',
                'cancel_at_period_end' => 0,
            ]
        );

        self::assertSame('active', $subscription['status']);
        self::assertSame('monthly', $subscription['plan']);
        self::assertTrue($subscription['auto_renew']);
        self::assertFalse($subscription['cancel_at_period_end']);
    }

    public function testScheduledCancellationKeepsAccessUntilPaidPeriodEnd(): void
    {
        $subscription = compute_gym_subscription_state(
            ['role' => 'operatore'],
            [
                'trial_start_date' => null,
                'status' => 'canceled',
                'plan' => 'monthly',
                'current_period_end' => date('Y-m-d H:i:s', time() + 3600),
                'provider' => 'stripe',
                'cancel_at_period_end' => 1,
            ]
        );

        self::assertSame('active', $subscription['status']);
        self::assertFalse($subscription['auto_renew']);
        self::assertTrue($subscription['cancel_at_period_end']);
    }

    public function testPastDueSubscriptionKeepsAccessOnlyUntilCurrentPeriodEnd(): void
    {
        $future = compute_gym_subscription_state(
            ['role' => 'operatore'],
            [
                'trial_start_date' => null,
                'status' => 'past_due',
                'plan' => 'monthly',
                'current_period_end' => date('Y-m-d H:i:s', time() + 3600),
                'provider' => 'stripe',
                'cancel_at_period_end' => 0,
            ]
        );
        $past = compute_gym_subscription_state(
            ['role' => 'operatore'],
            [
                'trial_start_date' => null,
                'status' => 'past_due',
                'plan' => 'monthly',
                'current_period_end' => date('Y-m-d H:i:s', time() - 3600),
                'provider' => 'stripe',
                'cancel_at_period_end' => 0,
            ]
        );

        self::assertSame('active', $future['status']);
        self::assertSame('expired', $past['status']);
    }
}
