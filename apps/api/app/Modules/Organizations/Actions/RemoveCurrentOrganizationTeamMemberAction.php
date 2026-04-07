<?php

namespace App\Modules\Organizations\Actions;

use App\Modules\Organizations\Enums\OrganizationType;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Partners\Actions\RebuildPartnerStatsAction;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveCurrentOrganizationTeamMemberAction
{
    public function __construct(
        private readonly RebuildPartnerStatsAction $rebuildPartnerStats,
    ) {}

    public function execute(Organization $organization, OrganizationMember $member, User $actor): void
    {
        if ((int) $member->organization_id !== (int) $organization->id) {
            throw ValidationException::withMessages([
                'member' => 'O membro informado nao pertence a organizacao atual.',
            ]);
        }

        if ($member->is_owner || (int) $member->user_id === (int) $actor->id) {
            throw ValidationException::withMessages([
                'member' => 'Nao e permitido remover o proprietario da organizacao atual.',
            ]);
        }

        DB::transaction(function () use ($organization, $member, $actor) {
            $removedMemberId = $member->id;
            $removedUserId = $member->user_id;
            $removedRoleKey = $member->role_key;

            $member->delete();

            if (($organization->type?->value ?? $organization->type) === OrganizationType::Partner->value) {
                $this->rebuildPartnerStats->execute($organization->fresh(['subscriptions.plan']));
            }

            activity()
                ->event('organization.team.removed')
                ->performedOn($organization)
                ->causedBy($actor)
                ->withProperties([
                    'organization_id' => $organization->id,
                    'member_id' => $removedMemberId,
                    'user_id' => $removedUserId,
                    'role_key' => $removedRoleKey,
                ])
                ->log('Membro removido da organizacao');
        });
    }
}
