<?php

namespace App\Modules\Partners\Policies;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Users\Models\User;

class PartnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isGlobalAdmin($user) || $this->canView($user);
    }

    public function view(User $user, Organization $partner): bool
    {
        return $this->isPartner($partner) && ($this->isGlobalAdmin($user) || $this->canView($user));
    }

    public function create(User $user): bool
    {
        return $this->isGlobalAdmin($user) || $user->can('partners.manage.any');
    }

    public function update(User $user, Organization $partner): bool
    {
        return $this->isPartner($partner) && ($this->isGlobalAdmin($user) || $user->can('partners.manage.any'));
    }

    public function suspend(User $user, Organization $partner): bool
    {
        return $this->update($user, $partner);
    }

    public function delete(User $user, Organization $partner): bool
    {
        return $this->update($user, $partner);
    }

    public function manageStaff(User $user, Organization $partner): bool
    {
        return $this->update($user, $partner);
    }

    public function manageGrants(User $user, Organization $partner): bool
    {
        return $this->update($user, $partner);
    }

    private function canView(User $user): bool
    {
        return $user->can('partners.view.any') || $user->can('partners.manage.any');
    }

    private function isGlobalAdmin(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'platform-admin']);
    }

    private function isPartner(Organization $organization): bool
    {
        return $organization->type?->value === 'partner' || $organization->type === 'partner';
    }
}
