<?php

namespace App\Modules\Organizations\Actions;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferCurrentOrganizationOwnershipAction
{
    public function execute(Organization $organization, OrganizationMember $targetMember, User $actor): OrganizationMember
    {
        if ((int) $targetMember->organization_id !== (int) $organization->id) {
            throw ValidationException::withMessages([
                'member_id' => 'Selecione um membro ativo da organizacao atual.',
            ]);
        }

        if ($targetMember->status !== 'active') {
            throw ValidationException::withMessages([
                'member_id' => 'A titularidade so pode ser transferida para um membro ativo.',
            ]);
        }

        return DB::transaction(function () use ($organization, $targetMember, $actor) {
            OrganizationMember::query()
                ->where('organization_id', $organization->id)
                ->where('is_owner', true)
                ->whereKeyNot($targetMember->id)
                ->update([
                    'is_owner' => false,
                    'role_key' => 'partner-manager',
                ]);

            $targetMember->forceFill([
                'is_owner' => true,
                'role_key' => 'partner-owner',
                'status' => 'active',
                'joined_at' => $targetMember->joined_at ?? now(),
            ])->save();

            $targetMember->user?->assignRole('partner-owner');
            $this->downgradeActorRoleWhenSafe($actor, $organization);

            activity()
                ->performedOn($organization)
                ->causedBy($actor)
                ->withProperties([
                    'organization_id' => $organization->id,
                    'new_owner_member_id' => $targetMember->id,
                    'new_owner_user_id' => $targetMember->user_id,
                ])
                ->log('Titularidade da organizacao transferida');

            return $targetMember->fresh()->load('user:id,name,email,phone,avatar_path');
        });
    }

    private function downgradeActorRoleWhenSafe(User $actor, Organization $organization): void
    {
        if ($actor->hasAnyRole(['super-admin', 'platform-admin'])) {
            return;
        }

        $actor->assignRole('partner-manager');

        $ownsAnotherOrganization = $actor->organizationMembers()
            ->where('organization_id', '<>', $organization->id)
            ->where('role_key', 'partner-owner')
            ->where('status', 'active')
            ->exists();

        if (! $ownsAnotherOrganization && $actor->hasRole('partner-owner')) {
            $actor->removeRole('partner-owner');
        }
    }
}
