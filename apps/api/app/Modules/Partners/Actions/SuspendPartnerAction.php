<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Partners\Support\PartnerProjectionTables;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;

class SuspendPartnerAction
{
    public function __construct(
        private readonly RebuildPartnerStatsAction $rebuildPartnerStats,
    ) {}

    public function execute(Organization $partner, array $data, User $actor): Organization
    {
        return DB::transaction(function () use ($partner, $data, $actor) {
            $partner->update(['status' => 'suspended']);

            $this->rebuildPartnerStats->execute($partner->fresh(['subscriptions.plan']));

            activity()
                ->event('partner.suspended')
                ->performedOn($partner)
                ->causedBy($actor)
                ->withProperties([
                    'partner_id' => $partner->id,
                    'organization_id' => $partner->id,
                    'reason' => $data['reason'],
                    'notes' => $data['notes'] ?? null,
                ])
                ->log('Parceiro suspenso');

            return $partner->fresh(PartnerProjectionTables::loadableOrganizationRelations());
        });
    }
}
