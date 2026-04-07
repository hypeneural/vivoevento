<?php

namespace App\Modules\Partners\Support;

use Illuminate\Support\Facades\Schema;

class PartnerProjectionTables
{
    public static function hasProfilesTable(): bool
    {
        return Schema::hasTable('partner_profiles');
    }

    public static function hasStatsTable(): bool
    {
        return Schema::hasTable('partner_stats');
    }

    public static function loadableOrganizationRelations(): array
    {
        $relations = [
            'subscription.plan',
            'members.user',
        ];

        if (self::hasProfilesTable()) {
            $relations[] = 'partnerProfile';
        }

        if (self::hasStatsTable()) {
            $relations[] = 'partnerStats';
        }

        return $relations;
    }
}
