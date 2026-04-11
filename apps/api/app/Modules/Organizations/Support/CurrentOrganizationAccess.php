<?php

namespace App\Modules\Organizations\Support;

use App\Modules\Users\Models\User;

class CurrentOrganizationAccess
{
    public static function canManageSettings(?User $user): bool
    {
        if (! self::hasCurrentOrganization($user)) {
            return false;
        }

        if (self::isGlobalAdmin($user)) {
            return true;
        }

        $membership = self::currentMembership($user);

        return in_array((string) $membership?->role_key, ['partner-owner', 'partner-manager'], true);
    }

    public static function canManageBranding(?User $user): bool
    {
        if (! self::hasCurrentOrganization($user)) {
            return false;
        }

        if (self::isGlobalAdmin($user)) {
            return true;
        }

        $membership = self::currentMembership($user);

        return (string) $membership?->role_key === 'partner-owner';
    }

    public static function canManageTeam(?User $user): bool
    {
        if (! self::hasCurrentOrganization($user)) {
            return false;
        }

        if (self::isGlobalAdmin($user)) {
            return true;
        }

        $membership = self::currentMembership($user);

        return in_array((string) $membership?->role_key, ['partner-owner', 'partner-manager'], true);
    }

    public static function canTransferOwnership(?User $user): bool
    {
        if (! self::hasCurrentOrganization($user)) {
            return false;
        }

        if (self::isGlobalAdmin($user)) {
            return true;
        }

        $membership = self::currentMembership($user);

        return (bool) $membership?->is_owner
            && (string) $membership?->role_key === 'partner-owner';
    }

    private static function hasCurrentOrganization(?User $user): bool
    {
        return $user !== null && $user->currentOrganization() !== null;
    }

    private static function isGlobalAdmin(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->hasAnyRole(['super-admin', 'platform-admin']);
    }

    private static function currentMembership(?User $user): ?\App\Modules\Organizations\Models\OrganizationMember
    {
        $organization = $user?->currentOrganization();

        if (! $user || ! $organization) {
            return null;
        }

        return $user->organizationMembers()
            ->active()
            ->where('organization_id', $organization->id)
            ->first();
    }
}
