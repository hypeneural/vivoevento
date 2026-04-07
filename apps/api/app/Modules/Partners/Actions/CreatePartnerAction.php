<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Organizations\Enums\OrganizationType;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Partners\Models\PartnerProfile;
use App\Modules\Users\Models\User;
use App\Shared\Support\Helpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePartnerAction
{
    public function __construct(
        private readonly RebuildPartnerStatsAction $rebuildPartnerStats,
    ) {}

    public function execute(array $data, User $actor): Organization
    {
        return DB::transaction(function () use ($data, $actor) {
            $partner = Organization::query()->create([
                'type' => OrganizationType::Partner->value,
                'trade_name' => $data['name'],
                'legal_name' => $data['legal_name'] ?? null,
                'document_number' => $data['document_number'] ?? null,
                'slug' => Helpers::generateUniqueSlug($data['name'], Organization::class),
                'email' => $data['email'],
                'billing_email' => $data['billing_email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'timezone' => $data['timezone'] ?? 'America/Sao_Paulo',
                'status' => $data['status'] ?? 'active',
            ]);

            $ownerUser = User::query()->firstOrCreate(
                ['email' => $data['owner']['email']],
                [
                    'name' => $data['owner']['name'],
                    'phone' => $data['owner']['phone'] ?? null,
                    'password' => Str::random(40),
                    'status' => 'active',
                ],
            );

            $ownerUser->fill([
                'name' => $ownerUser->name ?: $data['owner']['name'],
                'phone' => $ownerUser->phone ?: ($data['owner']['phone'] ?? null),
            ])->save();

            if (! $ownerUser->hasRole('partner-owner')) {
                $ownerUser->assignRole('partner-owner');
            }

            OrganizationMember::query()->updateOrCreate(
                [
                    'organization_id' => $partner->id,
                    'user_id' => $ownerUser->id,
                ],
                [
                    'role_key' => 'partner-owner',
                    'is_owner' => true,
                    'invited_by' => $actor->id,
                    'status' => 'active',
                    'invited_at' => now(),
                    'joined_at' => now(),
                ],
            );

            PartnerProfile::query()->updateOrCreate(
                ['organization_id' => $partner->id],
                [
                    'segment' => $data['segment'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'account_owner_user_id' => $ownerUser->id,
                ],
            );

            $this->rebuildPartnerStats->execute($partner->fresh(['subscriptions.plan']));

            activity()
                ->event('partner.created')
                ->performedOn($partner)
                ->causedBy($actor)
                ->withProperties([
                    'partner_id' => $partner->id,
                    'organization_id' => $partner->id,
                    'owner_user_id' => $ownerUser->id,
                    'segment' => $data['segment'] ?? null,
                ])
                ->log('Parceiro criado');

            return $partner->fresh([
                'partnerProfile',
                'partnerStats',
                'subscription.plan',
                'members.user',
            ]);
        });
    }
}
