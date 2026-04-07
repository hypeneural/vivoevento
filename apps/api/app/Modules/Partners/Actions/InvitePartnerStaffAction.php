<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvitePartnerStaffAction
{
    public function __construct(
        private readonly RebuildPartnerStatsAction $rebuildPartnerStats,
    ) {}

    public function execute(Organization $partner, array $data, User $actor): OrganizationMember
    {
        return DB::transaction(function () use ($partner, $data, $actor) {
            $staffUser = User::query()->firstOrCreate(
                ['email' => $data['user']['email']],
                [
                    'name' => $data['user']['name'],
                    'phone' => $data['user']['phone'] ?? null,
                    'password' => Str::random(40),
                    'status' => 'active',
                ],
            );

            $staffUser->fill([
                'name' => $staffUser->name ?: $data['user']['name'],
                'phone' => $staffUser->phone ?: ($data['user']['phone'] ?? null),
            ])->save();

            if (! $staffUser->hasRole($data['role_key'])) {
                $staffUser->assignRole($data['role_key']);
            }

            $membership = OrganizationMember::query()->updateOrCreate(
                [
                    'organization_id' => $partner->id,
                    'user_id' => $staffUser->id,
                ],
                [
                    'role_key' => $data['role_key'],
                    'is_owner' => (bool) ($data['is_owner'] ?? false),
                    'invited_by' => $actor->id,
                    'status' => 'active',
                    'invited_at' => now(),
                    'joined_at' => now(),
                ],
            );

            $this->rebuildPartnerStats->execute($partner->fresh(['subscriptions.plan']));

            activity()
                ->event('partner.staff.invited')
                ->performedOn($partner)
                ->causedBy($actor)
                ->withProperties([
                    'partner_id' => $partner->id,
                    'organization_id' => $partner->id,
                    'user_id' => $staffUser->id,
                    'role_key' => $membership->role_key,
                ])
                ->log('Staff do parceiro convidado');

            return $membership->load('user:id,name,email,phone');
        });
    }
}
