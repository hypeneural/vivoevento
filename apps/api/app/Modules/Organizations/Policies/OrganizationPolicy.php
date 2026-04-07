<?php

namespace App\Modules\Organizations\Policies;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Users\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isGlobalAdmin($user) && $user->can('organizations.view');
    }

    public function view(User $user, Organization $organization): bool
    {
        return $this->isGlobalAdmin($user) && $user->can('organizations.view');
    }

    public function create(User $user): bool
    {
        return $this->isGlobalAdmin($user) && $user->can('organizations.create');
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->isGlobalAdmin($user) && $user->can('organizations.update');
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $this->isGlobalAdmin($user) && $user->can('organizations.delete');
    }

    private function isGlobalAdmin(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'platform-admin']);
    }
}
