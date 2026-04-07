<?php

namespace App\Modules\Organizations\Support;

use App\Modules\Users\Models\User;

class CurrentOrganizationAccess
{
    public static function canManageSettings(?User $user): bool
    {
        return self::hasCurrentOrganization($user)
            && self::hasPermissionOrGlobalAdmin($user, 'settings.manage');
    }

    public static function canManageBranding(?User $user): bool
    {
        return self::hasCurrentOrganization($user)
            && self::hasPermissionOrGlobalAdmin($user, 'branding.manage');
    }

    public static function canManageTeam(?User $user): bool
    {
        return self::hasCurrentOrganization($user)
            && self::hasPermissionOrGlobalAdmin($user, 'team.manage');
    }

    private static function hasCurrentOrganization(?User $user): bool
    {
        return $user !== null && $user->currentOrganization() !== null;
    }

    private static function hasPermissionOrGlobalAdmin(?User $user, string $permission): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->can($permission)
            || $user->hasAnyRole(['super-admin', 'platform-admin']);
    }
}
