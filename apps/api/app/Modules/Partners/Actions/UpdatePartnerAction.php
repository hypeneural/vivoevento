<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Partners\Models\PartnerProfile;
use App\Modules\Partners\Support\PartnerProjectionTables;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;

class UpdatePartnerAction
{
    public function __construct(
        private readonly RebuildPartnerStatsAction $rebuildPartnerStats,
    ) {}

    public function execute(Organization $partner, array $data, User $actor): Organization
    {
        return DB::transaction(function () use ($partner, $data, $actor) {
            $partner->fill([
                'trade_name' => $data['name'] ?? $partner->trade_name,
                'legal_name' => $data['legal_name'] ?? $partner->legal_name,
                'document_number' => $data['document_number'] ?? $partner->document_number,
                'email' => $data['email'] ?? $partner->email,
                'billing_email' => $data['billing_email'] ?? $partner->billing_email,
                'phone' => $data['phone'] ?? $partner->phone,
                'timezone' => $data['timezone'] ?? $partner->timezone,
                'status' => $data['status'] ?? $partner->status,
            ])->save();

            if (PartnerProjectionTables::hasProfilesTable() && (
                array_key_exists('segment', $data)
                || array_key_exists('notes', $data)
                || array_key_exists('business_stage', $data)
            )) {
                $partner->loadMissing('partnerProfile');

                PartnerProfile::query()->updateOrCreate(
                    ['organization_id' => $partner->id],
                    [
                        'segment' => $data['segment'] ?? $partner->partnerProfile?->segment,
                        'notes' => $data['notes'] ?? $partner->partnerProfile?->notes,
                        'business_stage' => $data['business_stage'] ?? $partner->partnerProfile?->business_stage,
                        'account_owner_user_id' => $partner->partnerProfile?->account_owner_user_id,
                    ],
                );
            }

            $this->rebuildPartnerStats->execute($partner->fresh(['subscriptions.plan']));

            activity()
                ->event('partner.updated')
                ->performedOn($partner)
                ->causedBy($actor)
                ->withProperties([
                    'partner_id' => $partner->id,
                    'organization_id' => $partner->id,
                    'attributes' => $data,
                ])
                ->log('Parceiro atualizado');

            return $partner->fresh(PartnerProjectionTables::loadableOrganizationRelations());
        });
    }
}
