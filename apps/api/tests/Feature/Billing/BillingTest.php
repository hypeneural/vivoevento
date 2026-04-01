<?php

// ─── Billing ─────────────────────────────────────────────

it('returns current subscription (null when none)', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiGet('/billing/subscription');

    $this->assertApiSuccess($response);
    // No subscription yet
    expect($response->json('data'))->toBeNull();
});

it('creates subscription via checkout', function () {
    [$user, $organization] = $this->actingAsOwner();

    // Create a plan first
    $plan = \App\Modules\Plans\Models\Plan::create([
        'name' => 'Pro Parceiro',
        'slug' => 'pro-parceiro',
        'type' => 'recurring',
        'price_monthly_cents' => 29900,
        'is_active' => true,
    ]);

    $response = $this->apiPost('/billing/checkout', [
        'plan_id' => $plan->id,
        'billing_cycle' => 'monthly',
    ]);

    $this->assertApiSuccess($response, 201);

    $response->assertJsonStructure([
        'data' => [
            'subscription_id',
            'plan_name',
            'status',
            'starts_at',
            'renews_at',
        ],
    ]);

    expect($response->json('data.plan_name'))->toBe('Pro Parceiro');
    expect($response->json('data.status'))->toBe('active');
});

it('returns current plan after checkout', function () {
    [$user, $organization] = $this->actingAsOwner();

    $plan = \App\Modules\Plans\Models\Plan::create([
        'name' => 'Pro Parceiro',
        'slug' => 'pro-parceiro',
        'type' => 'recurring',
        'price_monthly_cents' => 29900,
        'is_active' => true,
    ]);

    $this->apiPost('/billing/checkout', [
        'plan_id' => $plan->id,
    ]);

    $response = $this->apiGet('/plans/current');

    $this->assertApiSuccess($response);
});

it('validates checkout requires plan_id', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiPost('/billing/checkout', []);

    $this->assertApiValidationError($response, ['plan_id']);
});

it('rejects billing access for unauthenticated user', function () {
    $response = $this->apiGet('/billing/subscription');

    $this->assertApiUnauthorized($response);
});
