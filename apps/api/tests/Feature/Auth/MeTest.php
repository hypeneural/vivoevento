<?php

// ─── /auth/me — the most important endpoint ──────────────

it('returns rich session payload for authenticated user', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiGet('/auth/me');

    $this->assertApiSuccess($response);

    $response->assertJsonStructure([
        'data' => [
            'user' => [
                'id', 'name', 'email', 'role', 'permissions', 'preferences',
            ],
            'organization',
            'access' => [
                'accessible_modules',
                'feature_flags',
            ],
        ],
        'meta' => ['request_id'],
    ]);

    // Verify user data
    $data = $response->json('data');
    expect($data['user']['id'])->toBe($user->id);
    expect($data['user']['role'])->toHaveKeys(['key', 'name']);
    expect($data['user']['permissions'])->toBeArray();
    expect($data['user']['preferences'])->toHaveKeys(['theme', 'timezone', 'locale']);
});

it('returns organization with branding in /me', function () {
    [$user, $organization] = $this->actingAsOwner();

    $organization->update([
        'primary_color' => '#7c3aed',
        'secondary_color' => '#3b82f6',
    ]);

    $response = $this->apiGet('/auth/me');
    $this->assertApiSuccess($response);

    $orgData = $response->json('data.organization');
    expect($orgData)->not->toBeNull();
    expect($orgData['branding']['primary_color'])->toBe('#7c3aed');
});

it('returns access matrix with modules and feature flags', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiGet('/auth/me');
    $this->assertApiSuccess($response);

    $access = $response->json('data.access');
    expect($access['accessible_modules'])->toBeArray();
    expect($access['accessible_modules'])->toContain('dashboard');
    expect($access['accessible_modules'])->toContain('events');
    expect($access['feature_flags'])->toBeArray();
    expect($access['feature_flags'])->toHaveKey('live_gallery');
});

it('returns partner-owner permissions for owner', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiGet('/auth/me');

    $permissions = $response->json('data.user.permissions');
    expect($permissions)->toContain('events.view');
    expect($permissions)->toContain('events.create');
    expect($permissions)->toContain('media.moderate');
    expect($permissions)->toContain('billing.view');
});

it('returns limited permissions for viewer', function () {
    [$user, $organization] = $this->actingAsViewer();

    $response = $this->apiGet('/auth/me');
    $this->assertApiSuccess($response);

    $permissions = $response->json('data.user.permissions');
    expect($permissions)->toContain('events.view');
    expect($permissions)->not->toContain('events.create');
    expect($permissions)->not->toContain('billing.manage');
});

it('rejects /me for unauthenticated user', function () {
    $response = $this->apiGet('/auth/me');

    $this->assertApiUnauthorized($response);
});

it('allows updating user preferences', function () {
    [$user] = $this->actingAsOwner();

    $response = $this->apiPatch('/auth/me', [
        'name' => 'Rafael Updated',
        'preferences' => [
            'theme' => 'dark',
            'locale' => 'en',
        ],
    ]);

    $this->assertApiSuccess($response);

    $user->refresh();
    expect($user->name)->toBe('Rafael Updated');
    expect($user->preferences['theme'])->toBe('dark');
});
