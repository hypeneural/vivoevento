<?php

use App\Modules\Billing\Services\Pagarme\PagarmePlanPayloadFactory;
use App\Modules\Plans\Models\Plan;
use App\Modules\Plans\Models\PlanPrice;

it('builds a recurring pagarme plan payload from the local plan price configuration', function () {
    config()->set('services.pagarme.statement_descriptor', 'EVENTOVIVO');

    $plan = Plan::create([
        'code' => 'partner-pro',
        'name' => 'Partner Pro',
        'audience' => 'b2b',
        'status' => 'active',
        'description' => 'Plano recorrente para parceiros.',
    ]);

    $planPrice = PlanPrice::create([
        'plan_id' => $plan->id,
        'billing_cycle' => 'monthly',
        'currency' => 'BRL',
        'amount_cents' => 19900,
        'billing_type' => 'exact_day',
        'billing_day' => 12,
        'trial_period_days' => 7,
        'payment_methods_json' => ['credit_card', 'boleto'],
        'is_default' => true,
    ]);

    $payload = app(PagarmePlanPayloadFactory::class)->build($plan, $planPrice, [
        'journey' => 'subscription_checkout',
    ]);

    expect($payload['name'])->toBe('Partner Pro Mensal')
        ->and($payload['payment_methods'])->toBe(['credit_card', 'boleto'])
        ->and($payload['installments'])->toBe([1])
        ->and($payload['interval'])->toBe('month')
        ->and($payload['interval_count'])->toBe(1)
        ->and($payload['trial_period_days'])->toBe(7)
        ->and($payload['billing_type'])->toBe('exact_day')
        ->and($payload['billing_days'])->toBe([12])
        ->and(data_get($payload, 'items.0.pricing_scheme.scheme_type'))->toBe('unit')
        ->and(data_get($payload, 'items.0.pricing_scheme.price'))->toBe(19900)
        ->and(data_get($payload, 'metadata.plan_price_id'))->toBe((string) $planPrice->id)
        ->and(data_get($payload, 'metadata.plan_code'))->toBe('partner-pro');
});
