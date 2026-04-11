<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;

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

it('uploads the current organization logo as a normalized webp asset', function () {
    Storage::fake('public');

    [$user, $organization] = $this->actingAsOwner();

    $response = $this->withHeaders($this->defaultHeaders())
        ->post('/api/v1/organizations/current/branding/logo', [
            'logo' => UploadedFile::fake()->image('logo.jpg', 1400, 900),
        ]);

    $this->assertApiSuccess($response);

    $organization->refresh();

    expect($organization->logo_path)->not->toBeNull();
    expect($organization->logo_path)->toEndWith('.webp');
    expect(Storage::disk('public')->exists($organization->logo_path))->toBeTrue();
    expect($response->json('data.logo_url'))->toBe(Storage::disk('public')->url($organization->logo_path));
});

it('updates current organization details', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiPatch('/organizations/current', [
        'name' => 'Novo Nome',
        'billing_email' => 'finance@test.com',
        'slug' => 'novo-nome',
    ]);

    $this->assertApiSuccess($response);

    $organization->refresh();
    expect($organization->trade_name)->toBe('Novo Nome');
    expect($organization->billing_email)->toBe('finance@test.com');
    expect($organization->slug)->toBe('novo-nome');
});

it('lists organization team', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiGet('/organizations/current/team');

    $this->assertApiSuccess($response);
    expect($response->json('data'))->toBeArray();
});

it('creates pending team invitations and still removes active team members through current organization settings', function () {
    [$user, $organization] = $this->actingAsOwner();

    $inviteResponse = $this->apiPost('/organizations/current/team', [
        'user' => [
            'name' => 'Equipe Nova',
            'email' => 'equipe-nova@eventovivo.test',
            'phone' => '11999998888',
        ],
        'role_key' => 'partner-manager',
        'send_via_whatsapp' => false,
    ]);

    $this->assertApiSuccess($inviteResponse, 201);

    $this->assertDatabaseHas('organization_member_invitations', [
        'id' => $inviteResponse->json('data.id'),
        'organization_id' => $organization->id,
        'role_key' => 'partner-manager',
        'status' => 'pending',
    ]);

    $member = \App\Modules\Organizations\Models\OrganizationMember::query()->create([
        'organization_id' => $organization->id,
        'user_id' => \App\Modules\Users\Models\User::factory()->create([
            'email' => 'equipe-ativa@eventovivo.test',
            'phone' => '5511999990001',
        ])->id,
        'role_key' => 'partner-manager',
        'is_owner' => false,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $this->assertDatabaseHas('organization_members', [
        'id' => $member->id,
    ]);

    $deleteResponse = $this->apiDelete("/organizations/current/team/{$member->id}");

    $deleteResponse->assertNoContent();

    $this->assertDatabaseMissing('organization_members', [
        'id' => $member->id,
    ]);
});

it('requires whatsapp phone when inviting organization team members', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiPost('/organizations/current/team', [
        'user' => [
            'name' => 'Equipe Sem WhatsApp',
            'email' => 'sem-whatsapp@eventovivo.test',
        ],
        'role_key' => 'partner-manager',
        'is_owner' => false,
    ]);

    $this->assertApiValidationError($response, ['user.phone']);
});

it('does not allow removing the owner membership from current organization settings', function () {
    [$user, $organization] = $this->actingAsOwner();

    $ownerMembership = \App\Modules\Organizations\Models\OrganizationMember::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->firstOrFail();

    $response = $this->apiDelete("/organizations/current/team/{$ownerMembership->id}");

    $this->assertApiValidationError($response, ['member']);
});

it('forbids viewers from managing current organization settings and team', function () {
    [$user, $organization] = $this->actingAsViewer();

    $this->assertApiForbidden(
        $this->apiPatch('/organizations/current', [
            'name' => 'Viewer Attempt',
        ]),
    );

    $this->assertApiForbidden(
        $this->apiPatch('/organizations/current/branding', [
            'primary_color' => '#111111',
        ]),
    );

    $this->assertApiForbidden($this->apiGet('/organizations/current/team'));
    $this->assertApiForbidden(
        $this->apiPost('/organizations/current/team', [
            'user' => [
                'name' => 'Nao Pode',
                'email' => 'nao-pode@eventovivo.test',
            ],
            'role_key' => 'viewer',
            'send_via_whatsapp' => false,
        ]),
    );
    $this->assertApiForbidden($this->apiGet('/organizations/current/team/invitations'));
});

it('allows super admins to manage current organization settings and team even when legacy settings permissions are missing', function () {
    [$user, $organization] = $this->actingAsSuperAdmin();

    $superAdminRole = Role::findByName('super-admin', 'web');
    $superAdminRole->revokePermissionTo([
        'settings.manage',
        'branding.manage',
        'team.manage',
    ]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->assertApiSuccess($this->apiGet('/organizations/current/team'));
    $this->assertApiSuccess($this->apiPatch('/organizations/current', [
        'name' => 'Org Super Admin',
    ]));
    $this->assertApiSuccess($this->apiPatch('/organizations/current/branding', [
        'primary_color' => '#123456',
    ]));
});

it('rejects organization access for unauthenticated user', function () {
    $response = $this->apiGet('/organizations/current');

    $this->assertApiUnauthorized($response);
});
