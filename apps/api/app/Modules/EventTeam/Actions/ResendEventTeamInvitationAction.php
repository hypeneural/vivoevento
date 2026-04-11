<?php

namespace App\Modules\EventTeam\Actions;

use App\Modules\EventTeam\Models\EventTeamInvitation;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ResendEventTeamInvitationAction
{
    public function __construct(
        private readonly DispatchEventTeamInvitationAction $dispatchAction,
    ) {}

    public function execute(EventTeamInvitation $invitation, User $actor, bool $sendViaWhatsApp): EventTeamInvitation
    {
        $invitation = DB::transaction(function () use ($invitation, $actor, $sendViaWhatsApp) {
            /** @var EventTeamInvitation $invitation */
            $invitation = EventTeamInvitation::query()
                ->with('event.organization')
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            if ($invitation->status === EventTeamInvitation::STATUS_ACCEPTED) {
                throw new ConflictHttpException('Este convite ja foi aceito e nao pode ser reenviado.');
            }

            if ($invitation->status === EventTeamInvitation::STATUS_REVOKED) {
                throw new ConflictHttpException('Este convite foi revogado e nao pode ser reenviado.');
            }

            $token = Str::random(64);

            $invitation->forceFill([
                'token' => $token,
                'token_expires_at' => now()->addDays(7),
                'invitation_url' => $this->invitationUrl($token),
                'delivery_channel' => $sendViaWhatsApp ? 'whatsapp' : 'manual',
                'delivery_status' => $sendViaWhatsApp ? 'pending_dispatch' : 'manual_link',
                'delivery_error' => null,
                'last_sent_at' => null,
            ])->save();

            activity()
                ->event('event.team.invitation.resent')
                ->performedOn($invitation->event)
                ->causedBy($actor)
                ->withProperties([
                    'event_id' => $invitation->event_id,
                    'invitation_id' => $invitation->id,
                    'delivery_channel' => $sendViaWhatsApp ? 'whatsapp' : 'manual',
                ])
                ->log('Convite pendente reenviado para equipe do evento');

            return $invitation;
        });

        return $this->dispatchAction->execute($invitation, $sendViaWhatsApp);
    }

    private function invitationUrl(string $token): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return "{$frontendUrl}/convites/eventos/{$token}";
    }
}
