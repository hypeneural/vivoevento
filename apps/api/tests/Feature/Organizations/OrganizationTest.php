<?php

// ─── Organization Current ────────────────────────────────

it('returns current organization', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiGet('/organizations/current');

    $this->assertApiSuccess($response);
    $response->assertJsonStructure([
        'data' => [
            'id', 'uuid', 'slug', 'status',
        ],
        'meta' => ['request_id'],
    ]);
});

it('updates current organization branding', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiPatch('/organizations/current/branding', [
        'primary_color' => '#ff6600',
        'secondary_color' => '#0066ff',
    ]);

    $this->assertApiSuccess($response);

    $organization->refresh();
    expect($organization->primary_color)->toBe('#ff6600');
    expect($organization->secondary_color)->toBe('#0066ff');
});

it('updates current organization details', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiPatch('/organizations/current', [
        'trade_name' => 'Novo Nome',
        'billing_email' => 'finance@test.com',
    ]);

    $this->assertApiSuccess($response);

    $organization->refresh();
    expect($organization->trade_name)->toBe('Novo Nome');
    expect($organization->billing_email)->toBe('finance@test.com');
});

// ─── Team ────────────────────────────────────────────────

it('lists organization team', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiGet('/organizations/current/team');

    $this->assertApiSuccess($response);
    expect($response->json('data'))->toBeArray();
});

// ─── Auth ────────────────────────────────────────────────

it('rejects organization access for unauthenticated user', function () {
    $response = $this->apiGet('/organizations/current');

    $this->assertApiUnauthorized($response);
});
