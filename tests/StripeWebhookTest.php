<?php

require_once __DIR__ . '/../inc/stripe_webhook.php';

use PHPUnit\Framework\TestCase;

class StripeWebhookTest extends TestCase
{
    public function testAcceptsValidSignature(): void
    {
        $timestamp = 1_700_000_000;
        $payload = '{"type":"checkout.session.completed"}';
        $secret = 'whsec_test_secret';
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        $this->assertTrue(
            verify_stripe_webhook_signature($payload, "t=$timestamp,v1=$signature", $secret, $timestamp)
        );
    }

    public function testRejectsTamperedPayloadAndExpiredSignature(): void
    {
        $timestamp = 1_700_000_000;
        $payload = '{"type":"checkout.session.completed"}';
        $secret = 'whsec_test_secret';
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        $header = "t=$timestamp,v1=$signature";

        $this->assertFalse(
            verify_stripe_webhook_signature($payload . ' ', $header, $secret, $timestamp)
        );
        $this->assertFalse(
            verify_stripe_webhook_signature($payload, $header, $secret, $timestamp + 301)
        );
    }

    public function testPeriodEndIsReadFromSubscriptionItemsOnNewApiVersions(): void
    {
        $this->assertSame(
            1794219737,
            stripe_subscription_period_end_timestamp([
                'items' => ['data' => [['current_period_end' => 1794219737]]],
            ])
        );
        $this->assertSame(
            1700000000,
            stripe_subscription_period_end_timestamp(['current_period_end' => 1700000000])
        );
        $this->assertSame(0, stripe_subscription_period_end_timestamp([]));
    }
}
