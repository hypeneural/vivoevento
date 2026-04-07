<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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
                'entitlements' => [
                    'modules',
                    'limits',
                    'branding',
                    'source_summary',
                ],
            ],
        ],
        'meta' => ['request_id'],
    ]);

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
    expect($access['entitlements'])->toBeArray();
    expect($access['entitlements'])->toHaveKey('modules');
    expect($access['entitlements'])->toHaveKey('limits');
});

it('returns partner-owner permissions for owner', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiGet('/auth/me');

    $permissions = $response->json('data.user.permissions');

    expect($permissions)->toContain('events.view');
    expect($permissions)->toContain('events.create');
    expect($permissions)->toContain('media.moderate');
    expect($permissions)->toContain('billing.view');
    expect($permissions)->toContain('settings.manage');
    expect($permissions)->toContain('branding.manage');
    expect($permissions)->toContain('team.manage');
});

it('returns manager settings permissions without elevated branding or partners access', function () {
    [$user, $organization] = $this->actingAsManager();

    $response = $this->apiGet('/auth/me');

    $this->assertApiSuccess($response);

    $permissions = $response->json('data.user.permissions');

    expect($permissions)->toContain('settings.manage');
    expect($permissions)->toContain('team.manage');
    expect($permissions)->not->toContain('branding.manage');
    expect($permissions)->not->toContain('partners.view.any');
    expect($permissions)->not->toContain('partners.manage.any');
});

it('returns the global super-admin role even with organization membership', function () {
    [$user, $organization] = $this->actingAsSuperAdmin();

    $response = $this->apiGet('/auth/me');

    $this->assertApiSuccess($response);

    expect($response->json('data.user.role.key'))->toBe('super-admin');
});

it('maps subscription code and feature flags using the real plan schema', function () {
    [$user, $organization] = $this->actingAsOwner();

    $plan = \App\Modules\Plans\Models\Plan::create([
        'code' => 'starter',
        'name' => 'Starter',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    $plan->features()->createMany([
        ['feature_key' => 'play.enabled', 'feature_value' => 'false'],
        ['feature_key' => 'wall.enabled', 'feature_value' => 'true'],
        ['feature_key' => 'white_label.enabled', 'feature_value' => 'false'],
        ['feature_key' => 'channels.whatsapp', 'feature_value' => 'true'],
    ]);

    \App\Modules\Billing\Models\Subscription::create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'starts_at' => now(),
        'renews_at' => now()->addMonth(),
    ]);

    $response = $this->apiGet('/auth/me');

    $this->assertApiSuccess($response);
    expect($response->json('data.subscription.plan_key'))->toBe('starter');
    expect($response->json('data.access.feature_flags.wall'))->toBeTrue();
    expect($response->json('data.access.feature_flags.play_memory'))->toBeFalse();
    expect($response->json('data.access.entitlements.modules.wall'))->toBeTrue();
    expect($response->json('data.access.entitlements.modules.play'))->toBeFalse();
    expect($response->json('data.access.entitlements.source_summary.0.plan_key'))->toBe('starter');
});

it('keeps access entitlements active while the subscription is canceled only at the end of the current period', function () {
    [$user, $organization] = $this->actingAsOwner();

    $plan = \App\Modules\Plans\Models\Plan::create([
        'code' => 'starter',
        'name' => 'Starter',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    $plan->features()->createMany([
        ['feature_key' => 'wall.enabled', 'feature_value' => 'true'],
        ['feature_key' => 'play.enabled', 'feature_value' => 'false'],
    ]);

    \App\Modules\Billing\Models\Subscription::create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'status' => 'canceled',
        'billing_cycle' => 'monthly',
        'starts_at' => now()->subDays(10),
        'canceled_at' => now(),
        'renews_at' => now()->addDays(20),
        'ends_at' => now()->addDays(20),
    ]);

    $response = $this->apiGet('/auth/me');

    $this->assertApiSuccess($response);
    expect($response->json('data.subscription.status'))->toBe('canceled');
    expect($response->json('data.access.entitlements.modules.wall'))->toBeTrue();
    expect($response->json('data.access.entitlements.source_summary.0.active'))->toBeTrue();
});

it('returns limited permissions for viewer', function () {
    [$user, $organization] = $this->actingAsViewer();

    $response = $this->apiGet('/auth/me');

    $this->assertApiSuccess($response);

    $permissions = $response->json('data.user.permissions');

    expect($permissions)->toContain('events.view');
    expect($permissions)->not->toContain('events.create');
    expect($permissions)->not->toContain('billing.manage');
    expect($permissions)->not->toContain('settings.manage');
    expect($permissions)->not->toContain('team.manage');
});

it('rejects /me for unauthenticated user', function () {
    $response = $this->apiGet('/auth/me');

    $this->assertApiUnauthorized($response);
});

it('allows updating user preferences', function () {
    [$user] = $this->actingAsOwner();

    $user->update([
        'preferences' => [
            'theme' => 'light',
            'locale' => 'pt-BR',
        ],
    ]);

    $response = $this->apiPatch('/auth/me', [
        'name' => 'Rafael Updated',
        'preferences' => [
            'email_notifications' => false,
            'push_notifications' => true,
            'compact_mode' => true,
        ],
    ]);

    $this->assertApiSuccess($response);

    $user->refresh();

    expect($user->name)->toBe('Rafael Updated');
    expect($user->preferences['theme'])->toBe('light');
    expect($user->preferences['locale'])->toBe('pt-BR');
    expect($user->preferences['email_notifications'])->toBeFalse();
    expect($user->preferences['push_notifications'])->toBeTrue();
    expect($user->preferences['compact_mode'])->toBeTrue();
    expect($response->json('data.user.preferences.email_notifications'))->toBeFalse();
    expect($response->json('data.user.preferences.push_notifications'))->toBeTrue();
    expect($response->json('data.user.preferences.compact_mode'))->toBeTrue();
});

it('allows updating the authenticated user password', function () {
    [$user] = $this->actingAsOwner();

    $response = $this->apiPatch('/auth/me/password', [
        'current_password' => 'password',
        'password' => 'NovaSenha123!',
        'password_confirmation' => 'NovaSenha123!',
    ]);

    $this->assertApiSuccess($response);

    $user->refresh();

    expect(Hash::check('NovaSenha123!', $user->password))->toBeTrue();
    expect($response->json('data.message'))->toBe('Senha atualizada com sucesso.');
});

it('rejects password update when the current password is invalid', function () {
    [$user] = $this->actingAsOwner();

    $response = $this->apiPatch('/auth/me/password', [
        'current_password' => 'senha-incorreta',
        'password' => 'NovaSenha123!',
        'password_confirmation' => 'NovaSenha123!',
    ]);

    $this->assertApiValidationError($response, ['current_password']);
});

it('normalizes uploaded avatars to square webp images', function () {
    Storage::fake('public');

    [$user] = $this->actingAsOwner();

    $response = $this->withHeaders($this->defaultHeaders())
        ->post('/api/v1/auth/me/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 1200, 800),
        ]);

    $this->assertApiSuccess($response);

    $user->refresh();

    expect($user->avatar_path)->not->toBeNull();
    expect($user->avatar_path)->toEndWith('.webp');
    expect(Storage::disk('public')->exists($user->avatar_path))->toBeTrue();
    expect($response->json('data.avatar_url'))->toBe(Storage::disk('public')->url($user->avatar_path));

    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($user->avatar_path));

    expect($width)->toBe(512);
    expect($height)->toBe(512);
});
