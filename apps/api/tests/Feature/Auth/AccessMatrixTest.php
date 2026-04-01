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

it('rejects access matrix for unauthenticated user', function () {
    $response = $this->apiGet('/access/matrix');

    $this->assertApiUnauthorized($response);
});
