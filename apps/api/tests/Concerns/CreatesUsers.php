<?php

namespace Tests\Concerns;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Users\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

trait CreatesUsers
{
    protected bool $permissionsSeeded = false;

    protected function seedPermissions(): void
    {
        if (!$this->permissionsSeeded) {
            $this->seed(RolesAndPermissionsSeeder::class);
            $this->permissionsSeeded = true;
        }
    }

    // ─── Factories ───────────────────────────────────────

    protected function createOrganization(array $attributes = []): Organization
    {
        return Organization::factory()->create($attributes);
    }

    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    // ─── actingAs helpers ────────────────────────────────

    /**
     * Create a user with partner-owner role attached to an organization.
     * Returns [User, Organization].
     */
    protected function actingAsOwner(?Organization $organization = null): array
    {
        $this->seedPermissions();

        $organization ??= $this->createOrganization();
        $user = $this->createUser();

        // Attach to org
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

        return [$user, $organization];
    }

    /**
     * Create a user with partner-manager role.
     */
    protected function actingAsManager(?Organization $organization = null): array
    {
        $this->seedPermissions();

        $organization ??= $this->createOrganization();
        $user = $this->createUser();

        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_key' => 'partner-manager',
            'is_owner' => false,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $user->assignRole('partner-manager');
        $this->actingAs($user);

        return [$user, $organization];
    }

    /**
     * Create a user with event-operator role.
     */
    protected function actingAsOperator(?Organization $organization = null): array
    {
        $this->seedPermissions();

        $organization ??= $this->createOrganization();
        $user = $this->createUser();

        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_key' => 'event-operator',
            'is_owner' => false,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $user->assignRole('event-operator');
        $this->actingAs($user);

        return [$user, $organization];
    }

    /**
     * Create a user with viewer role (minimal permissions).
     */
    protected function actingAsViewer(?Organization $organization = null): array
    {
        $this->seedPermissions();

        $organization ??= $this->createOrganization();
        $user = $this->createUser();

        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_key' => 'viewer',
            'is_owner' => false,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $user->assignRole('viewer');
        $this->actingAs($user);

        return [$user, $organization];
    }

    /**
     * Create a super-admin with an active organization membership.
     */
    protected function actingAsSuperAdmin(?Organization $organization = null): array
    {
        $this->seedPermissions();

        $organization ??= $this->createOrganization();
        $user = $this->createUser();

        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_key' => 'super-admin',
            'is_owner' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $user->assignRole('super-admin');
        $this->actingAs($user);

        return [$user, $organization];
    }
}
