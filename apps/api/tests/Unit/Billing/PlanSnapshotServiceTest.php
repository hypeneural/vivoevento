<?php

use App\Modules\Billing\Services\PlanSnapshotService;
use App\Modules\Plans\Models\Plan;
use App\Modules\Plans\Models\PlanFeature;
use App\Modules\Plans\Models\PlanPrice;

it('builds the checkout snapshot from the requested billing cycle', function () {
    $plan = Plan::create([
        'code' => 'professional',
        'name' => 'Professional',
        'audience' => 'b2b',
        'status' => 'active',
        'description' => 'Plano profissional.',
    ]);

    PlanPrice::create([
        'plan_id' => $plan->id,
        'billing_cycle' => 'monthly',
        'currency' => 'BRL',
        'amount_cents' => 9900,
        'gateway_provider' => 'pagarme',
        'gateway_price_id' => 'price_monthly_professional',
        'is_default' => true,
    ]);

    $yearlyPrice = PlanPrice::create([
        'plan_id' => $plan->id,
        'billing_cycle' => 'yearly',
        'currency' => 'BRL',
        'amount_cents' => 99900,
        'gateway_provider' => 'pagarme',
        'gateway_price_id' => 'price_yearly_professional',
        'is_default' => false,
    ]);

    PlanFeature::create([
        'plan_id' => $plan->id,
        'feature_key' => 'events.max_active',
        'feature_value' => '10',
    ]);

    $snapshot = app(PlanSnapshotService::class)->build($plan, 'yearly');

    expect(data_get($snapshot, 'price.id'))->toBe($yearlyPrice->id)
        ->and(data_get($snapshot, 'price.billing_cycle'))->toBe('yearly')
        ->and(data_get($snapshot, 'price.amount_cents'))->toBe(99900)
        ->and(data_get($snapshot, 'order_item_snapshot.price.gateway_price_id'))->toBe('price_yearly_professional')
        ->and(data_get($snapshot, 'feature_map.events.max_active'))->toBe('10');
});

it('falls back to the default price when the requested billing cycle is unavailable', function () {
    $plan = Plan::create([
        'code' => 'starter',
        'name' => 'Starter',
        'audience' => 'b2b',
        'status' => 'active',
        'description' => 'Plano inicial.',
    ]);

    $defaultPrice = PlanPrice::create([
        'plan_id' => $plan->id,
        'billing_cycle' => 'monthly',
        'currency' => 'BRL',
        'amount_cents' => 4900,
        'gateway_provider' => 'pagarme',
        'gateway_price_id' => 'price_monthly_starter',
        'is_default' => true,
    ]);

    $snapshot = app(PlanSnapshotService::class)->build($plan, 'yearly');

    expect(data_get($snapshot, 'price.id'))->toBe($defaultPrice->id)
        ->and(data_get($snapshot, 'price.billing_cycle'))->toBe('monthly')
        ->and(data_get($snapshot, 'order_item_snapshot.price.gateway_price_id'))->toBe('price_monthly_starter');
});
