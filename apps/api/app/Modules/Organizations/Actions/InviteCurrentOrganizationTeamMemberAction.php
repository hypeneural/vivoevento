<?php

namespace App\Modules\Organizations\Actions;

use App\Modules\Organizations\Enums\OrganizationType;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Partners\Actions\RebuildPartnerStatsAction;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InviteCurrentOrganizationTeamMemberAction
{
    public function __construct(
        private readonly RebuildPartnerStatsAction $rebuildPartnerStats,
    ) {}

    public function execute(Organization $organization, array $data, User $actor): OrganizationMember
    {
        return DB::transaction(function () use ($organization, $data, $actor) {
            $memberUser = User::query()->firstOrCreate(
                ['email' => $data['user']['email']],
                [
                    'name' => $data['user']['name'],
                    'phone' => $data['user']['phone'] ?? null,
                    'password' => Str::random(40),
                    'status' => 'active',
                ],
            );

            $memberUser->fill([
                'name' => $memberUser->name ?: $data['user']['name'],
                'phone' => $memberUser->phone ?: ($data['user']['phone'] ?? null),
            ])->save();

            if (! $memberUser->hasRole($data['role_key'])) {
                $memberUser->assignRole($data['role_key']);
            }

            $membership = OrganizationMember::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'user_id' => $memberUser->id,
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

            if (($organization->type?->value ?? $organization->type) === OrganizationType::Partner->value) {
                $this->rebuildPartnerStats->execute($organization->fresh(['subscriptions.plan']));
            }

            activity()
                ->event('organization.team.invited')
                ->performedOn($organization)
                ->causedBy($actor)
                ->withProperties([
                    'organization_id' => $organization->id,
                    'member_id' => $membership->id,
                    'user_id' => $memberUser->id,
                    'role_key' => $membership->role_key,
                ])
                ->log('Membro convidado para a organizacao');

            return $membership->load('user:id,name,email,phone,avatar_path');
        });
    }
}
