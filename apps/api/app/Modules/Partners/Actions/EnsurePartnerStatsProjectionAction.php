<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Organizations\Enums\OrganizationType;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Partners\Support\PartnerProjectionTables;

class EnsurePartnerStatsProjectionAction
{
    public function __construct(
        private readonly RebuildPartnerStatsAction $rebuildPartnerStats,
    ) {}

    public function execute(): void
    {
        if (! PartnerProjectionTables::hasStatsTable()) {
            return;
        }

        Organization::query()
            ->where('type', OrganizationType::Partner->value)
            ->whereDoesntHave('partnerStats')
            ->with(['subscriptions.plan'])
            ->orderBy('id')
            ->get()
            ->each(fn (Organization $partner) => $this->rebuildPartnerStats->execute($partner));
    }
}
