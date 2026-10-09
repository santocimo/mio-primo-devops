<?php

function subscription_plans(): array {
    $monthlyPrice = getenv('PLAN_MONTHLY_PRICE') ?: getenv('PAYPAL_PRICE_MONTHLY') ?: '4.99';
    $yearlyPrice = getenv('PLAN_YEARLY_PRICE') ?: getenv('PAYPAL_PRICE_YEARLY') ?: '49.99';
    $currency = strtoupper(getenv('PAYMENT_CURRENCY') ?: getenv('PAYPAL_CURRENCY') ?: 'EUR');

    if (!preg_match('/^\d{1,7}\.\d{2}$/', $monthlyPrice)
        || !preg_match('/^\d{1,7}\.\d{2}$/', $yearlyPrice)
        || !preg_match('/^[A-Z]{3}$/', $currency)) {
        throw new RuntimeException('Invalid subscription plan configuration');
    }

    return [
        'monthly' => [
            'price' => $monthlyPrice,
            'amount_minor' => (int)str_replace('.', '', $monthlyPrice),
            'duration_months' => 1,
            'interval' => 'month',
            'label' => 'SmartRegistry - Piano Mensile',
        ],
        'yearly' => [
            'price' => $yearlyPrice,
            'amount_minor' => (int)str_replace('.', '', $yearlyPrice),
            'duration_months' => 12,
            'interval' => 'year',
            'label' => 'SmartRegistry - Piano Annuale',
        ],
        '_currency' => $currency,
    ];
}

function public_subscription_plans(): array {
    $configured = subscription_plans();
    $currency = $configured['_currency'];
    unset($configured['_currency']);

    $plans = [];
    foreach ($configured as $id => $plan) {
        $plans[] = [
            'id' => 'businessregistry_' . $id,
            'name' => $plan['label'],
            'duration' => $id,
            'amount_minor' => $plan['amount_minor'],
            'currency' => $currency,
        ];
    }
    return $plans;
}
