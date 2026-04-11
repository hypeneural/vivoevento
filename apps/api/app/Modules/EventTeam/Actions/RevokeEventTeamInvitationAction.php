<?php

namespace App\Modules\EventTeam\Actions;

use App\Modules\EventTeam\Models\EventTeamInvitation;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RevokeEventTeamInvitationAction
{
    public function execute(EventTeamInvitation $invitation, User $actor): EventTeamInvitation
    {
        return DB::transaction(function () use ($invitation, $actor) {
            /** @var EventTeamInvitation $invitation */
            $invitation = EventTeamInvitation::query()
                ->with('event')
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            if ($invitation->status === EventTeamInvitation::STATUS_ACCEPTED) {
                throw new ConflictHttpException('Este convite ja foi aceito e nao pode ser revogado.');
            }

            if ($invitation->status !== EventTeamInvitation::STATUS_REVOKED) {
                $invitation->forceFill([
                    'status' => EventTeamInvitation::STATUS_REVOKED,
                    'revoked_at' => now(),
                    'delivery_status' => 'revoked',
                    'delivery_error' => null,
                ])->save();

                activity()
                    ->event('event.team.invitation.revoked')
                    ->performedOn($invitation->event)
                    ->causedBy($actor)
                    ->withProperties([
                        'event_id' => $invitation->event_id,
                        'invitation_id' => $invitation->id,
                    ])
                    ->log('Convite pendente revogado para equipe do evento');
            }

            return $invitation->fresh(['event.organization']);
        });
    }
}
