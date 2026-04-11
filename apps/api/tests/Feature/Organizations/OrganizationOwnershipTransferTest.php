<?php

use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Users\Models\User;

it('transfers current organization ownership to an active team member through a dedicated endpoint', function () {
    [$owner, $organization] = $this->actingAsOwner();

    $targetUser = User::factory()->create([
        'email' => 'secretaria@eventovivo.test',
        'phone' => '5511999000001',
    ]);

    $targetMembership = OrganizationMember::query()->create([
        'organization_id' => $organization->id,
        'user_id' => $targetUser->id,
        'role_key' => 'partner-manager',
        'is_owner' => false,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $response = $this->apiPost('/organizations/current/team/ownership-transfer', [
        'member_id' => $targetMembership->id,
    ]);

    $this->assertApiSuccess($response);

    expect($response->json('data.id'))->toBe($targetMembership->id);
    expect($response->json('data.is_owner'))->toBeTrue();
    expect($response->json('data.role_key'))->toBe('partner-owner');

    $this->assertDatabaseHas('organization_members', [
        'organization_id' => $organization->id,
        'user_id' => $targetUser->id,
        'role_key' => 'partner-owner',
        'is_owner' => true,
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('organization_members', [
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
        'role_key' => 'partner-manager',
        'is_owner' => false,
        'status' => 'active',
    ]);

    expect($targetUser->fresh()->hasRole('partner-owner'))->toBeTrue();
    expect($owner->fresh()->hasRole('partner-manager'))->toBeTrue();
    expect($owner->fresh()->hasRole('partner-owner'))->toBeFalse();
});

it('blocks managers from transferring current organization ownership', function () {
    [$manager, $organization] = $this->actingAsManager();

    $targetUser = User::factory()->create([
        'email' => 'destino@eventovivo.test',
        'phone' => '5511999000002',
    ]);

    $targetMembership = OrganizationMember::query()->create([
        'organization_id' => $organization->id,
        'user_id' => $targetUser->id,
        'role_key' => 'viewer',
        'is_owner' => false,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $response = $this->apiPost('/organizations/current/team/ownership-transfer', [
        'member_id' => $targetMembership->id,
    ]);

    $this->assertApiForbidden($response);
});

it('rejects ownership transfer to a member outside the current organization', function () {
    [$owner, $organization] = $this->actingAsOwner();
    $otherOrganization = $this->createOrganization();
    $targetUser = User::factory()->create([
        'email' => 'fora@eventovivo.test',
        'phone' => '5511999000003',
    ]);

    $outsideMembership = OrganizationMember::query()->create([
        'organization_id' => $otherOrganization->id,
        'user_id' => $targetUser->id,
        'role_key' => 'partner-manager',
        'is_owner' => false,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $response = $this->apiPost('/organizations/current/team/ownership-transfer', [
        'member_id' => $outsideMembership->id,
    ]);

    $this->assertApiValidationError($response, ['member_id']);
});
