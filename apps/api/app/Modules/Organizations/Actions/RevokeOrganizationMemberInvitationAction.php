<?php

namespace App\Modules\Organizations\Actions;

use App\Modules\Organizations\Models\OrganizationMemberInvitation;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RevokeOrganizationMemberInvitationAction
{
    public function execute(OrganizationMemberInvitation $invitation, User $actor): OrganizationMemberInvitation
    {
        return DB::transaction(function () use ($invitation, $actor) {
            /** @var OrganizationMemberInvitation $invitation */
            $invitation = OrganizationMemberInvitation::query()
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            if ($invitation->status !== OrganizationMemberInvitation::STATUS_PENDING) {
                throw new ConflictHttpException('Somente convites pendentes podem ser revogados.');
            }

            $invitation->forceFill([
                'status' => OrganizationMemberInvitation::STATUS_REVOKED,
                'revoked_at' => now(),
                'delivery_status' => 'revoked',
            ])->save();

            activity()
                ->event('organization.team.invitation.revoked')
                ->performedOn($invitation->organization)
                ->causedBy($actor)
                ->withProperties([
                    'organization_id' => $invitation->organization_id,
                    'invitation_id' => $invitation->id,
                    'role_key' => $invitation->role_key,
                ])
                ->log('Convite pendente revogado para equipe da organizacao');

            return $invitation->fresh('organization');
        });
    }
}
