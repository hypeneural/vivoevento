<?php

// ─── Access Matrix ──────────────────────────────────────

it('returns access matrix for authenticated user', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiGet('/access/matrix');

    $this->assertApiSuccess($response);

    $response->assertJsonStructure([
        'data' => [
            'roles',
            'permissions',
            'modules',
            'features',
            'entitlements',
        ],
        'meta' => ['request_id'],
    ]);

    $data = $response->json('data');
    expect($data['roles'])->toBeArray();
    expect($data['roles'][0])->toHaveKeys(['key', 'name']);
    expect($data['permissions'])->toBeArray();
    expect($data['modules'])->toBeArray();
    expect($data['modules'][0])->toHaveKeys(['key', 'enabled', 'visible']);
    expect($data['features'])->toBeArray();
    expect($data['entitlements'])->toBeArray();
    expect($data['entitlements'])->toHaveKeys(['modules', 'limits', 'branding', 'source_summary']);
});

it('derives access matrix features from organization entitlements', function () {
    [$user, $organization] = $this->actingAsOwner();

    $plan = \App\Modules\Plans\Models\Plan::create([
        'code' => 'business',
        'name' => 'Business',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    $plan->features()->createMany([
        ['feature_key' => 'wall.enabled', 'feature_value' => 'true'],
        ['feature_key' => 'play.enabled', 'feature_value' => 'true'],
        ['feature_key' => 'white_label.enabled', 'feature_value' => 'true'],
        ['feature_key' => 'custom_domain', 'feature_value' => 'true'],
        ['feature_key' => 'events.max_active', 'feature_value' => '50'],
    ]);

    \App\Modules\Billing\Models\Subscription::create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'starts_at' => now(),
        'renews_at' => now()->addMonth(),
    ]);

    $response = $this->apiGet('/access/matrix');

    $this->assertApiSuccess($response);
    expect($response->json('data.features.wall'))->toBeTrue();
    expect($response->json('data.features.play_memory'))->toBeTrue();
    expect($response->json('data.entitlements.branding.white_label'))->toBeTrue();
    expect($response->json('data.entitlements.limits.max_active_events'))->toBe(50);
});

it('returns different modules for viewer vs owner', function () {
    // Owner
    [$ownerUser, $org] = $this->actingAsOwner();
    $ownerResponse = $this->apiGet('/access/matrix');
    $ownerModules = collect($ownerResponse->json('data.modules'))
        ->where('enabled', true)->pluck('key')->all();

    // Viewer (new org to avoid conflicts)
    [$viewerUser, $org2] = $this->actingAsViewer();
    $viewerResponse = $this->apiGet('/access/matrix');
    $viewerModules = collect($viewerResponse->json('data.modules'))
        ->where('enabled', true)->pluck('key')->all();

    expect(count($ownerModules))->toBeGreaterThan(count($viewerModules));
    expect($viewerModules)->not->toContain('settings');
});

it('exposes the partners module when the user has partners.view.any without partners.manage.any', function () {
    [$user, $organization] = $this->actingAsViewer();

    $user->givePermissionTo('partners.view.any');

    $response = $this->apiGet('/access/matrix');

    $this->assertApiSuccess($response);

    $enabledModules = collect($response->json('data.modules'))
        ->where('enabled', true)
        ->pluck('key')
        ->all();

    expect($response->json('data.permissions'))->toContain('partners.view.any');
    expect($enabledModules)->toContain('partners');
});

it('rejects access matrix for unauthenticated user', function () {
    $response = $this->apiGet('/access/matrix');

    $this->assertApiUnauthorized($response);
});
