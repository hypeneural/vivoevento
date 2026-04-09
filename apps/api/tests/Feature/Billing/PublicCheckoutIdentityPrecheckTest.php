<?php

use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Users\Models\User;

it('returns new_account when the contact does not match an existing identity', function () {
    $response = $this->apiPost('/public/checkout-identity/check', [
        'whatsapp' => '(48) 99977-1111',
        'email' => 'camila@example.com',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.identity_status', 'new_account');
    $response->assertJsonPath('data.login_url', null);
    $response->assertJsonPath('data.action_label', null);
});

it('returns login_suggested when whatsapp already exists', function () {
    User::factory()->create([
        'phone' => '5548999771111',
    ]);

    $response = $this->apiPost('/public/checkout-identity/check', [
        'whatsapp' => '(48) 99977-1111',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.identity_status', 'login_suggested');
    $response->assertJsonPath('data.action_label', 'Entrar para continuar');
    $response->assertJsonPath('data.login_url', '/login?returnTo=%2Fcheckout%2Fevento%3Fv2%3D1%26resume%3Dauth');

    expect(mb_strtolower((string) $response->json('data.description')))
        ->not->toContain('whatsapp')
        ->not->toContain('e-mail');
});

it('returns login_suggested when email already exists', function () {
    User::factory()->create([
        'email' => 'camila@example.com',
    ]);

    $response = $this->apiPost('/public/checkout-identity/check', [
        'whatsapp' => '(48) 99977-1111',
        'email' => 'camila@example.com',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.identity_status', 'login_suggested');
    $response->assertJsonPath('data.title', 'Ja encontramos seu cadastro');
});

it('returns authenticated_match when the logged in user matches the informed contact', function () {
    $this->seedPermissions();

    $organization = Organization::factory()->create([
        'status' => 'active',
    ]);

    $user = User::factory()->create([
        'phone' => '5548999771111',
        'email' => 'camila@example.com',
        'status' => 'active',
    ]);

    OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role_key' => 'partner-owner',
        'is_owner' => true,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $user->assignRole('partner-owner');
    $this->actingAs($user);

    $response = $this->apiPost('/public/checkout-identity/check', [
        'whatsapp' => '(48) 99977-1111',
        'email' => 'camila@example.com',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.identity_status', 'authenticated_match');
    $response->assertJsonPath('data.login_url', null);
});

it('returns authenticated_mismatch when the logged in user informs another contact', function () {
    $this->seedPermissions();

    $organization = Organization::factory()->create([
        'status' => 'active',
    ]);

    $user = User::factory()->create([
        'phone' => '5548999771111',
        'email' => 'camila@example.com',
        'status' => 'active',
    ]);

    OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role_key' => 'partner-owner',
        'is_owner' => true,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $user->assignRole('partner-owner');
    $this->actingAs($user);

    $response = $this->apiPost('/public/checkout-identity/check', [
        'whatsapp' => '(48) 99988-2222',
        'email' => 'outra@example.com',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.identity_status', 'authenticated_mismatch');
    $response->assertJsonPath('data.login_url', null);
});

it('rate limits repeated identity pre-check requests for the same contact fingerprint', function () {
    foreach (range(1, 5) as $attempt) {
        $response = $this->apiPost('/public/checkout-identity/check', [
            'whatsapp' => '(48) 99955-4444',
            'email' => 'rate-limit@example.com',
        ]);

        $this->assertApiSuccess($response);
    }

    $blocked = $this->apiPost('/public/checkout-identity/check', [
        'whatsapp' => '(48) 99955-4444',
        'email' => 'rate-limit@example.com',
    ]);

    $blocked->assertStatus(429)
        ->assertJsonPath('success', false);

    expect((int) $blocked->json('cooldown_seconds'))->toBeGreaterThan(0);
});
