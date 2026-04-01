<?php

namespace App\Modules\Organizations\Actions;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Users\Models\User;

class InviteOrganizationMemberAction
{
    public function execute(Organization $organization, User $user, string $roleKey = 'member'): OrganizationMember
    {
        return OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_key' => $roleKey,
            'is_owner' => false,
            'invited_at' => now(),
        ]);
    }
}
