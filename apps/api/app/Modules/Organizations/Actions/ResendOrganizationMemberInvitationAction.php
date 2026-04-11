<?php

namespace App\Modules\Organizations\Actions;

use App\Modules\Organizations\Models\OrganizationMemberInvitation;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ResendOrganizationMemberInvitationAction
{
    public function __construct(
        private readonly DispatchOrganizationMemberInvitationAction $dispatchAction,
    ) {}

    public function execute(
        OrganizationMemberInvitation $invitation,
        User $actor,
        bool $sendViaWhatsApp,
    ): OrganizationMemberInvitation {
        $invitation = DB::transaction(function () use ($invitation, $actor, $sendViaWhatsApp) {
            /** @var OrganizationMemberInvitation $invitation */
            $invitation = OrganizationMemberInvitation::query()
                ->with('organization')
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            if ($invitation->status !== OrganizationMemberInvitation::STATUS_PENDING) {
                throw new ConflictHttpException('Somente convites pendentes podem ser reenviados.');
            }

            $token = Str::random(64);

            $invitation->forceFill([
                'token' => $token,
                'token_expires_at' => now()->addDays(7),
                'delivery_channel' => $sendViaWhatsApp ? 'whatsapp' : 'manual',
                'delivery_status' => $sendViaWhatsApp ? 'pending_dispatch' : 'manual_link',
                'delivery_error' => null,
                'invitation_url' => $this->invitationUrl($token),
                'last_sent_at' => null,
            ])->save();

            activity()
                ->event('organization.team.invitation.resent')
                ->performedOn($invitation->organization)
                ->causedBy($actor)
                ->withProperties([
                    'organization_id' => $invitation->organization_id,
                    'invitation_id' => $invitation->id,
                    'role_key' => $invitation->role_key,
                ])
                ->log('Convite pendente reenviado para equipe da organizacao');

            return $invitation;
        });

        return $this->dispatchAction->execute($invitation, $sendViaWhatsApp);
    }

    private function invitationUrl(string $token): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return "{$frontendUrl}/convites/equipe/{$token}";
    }
}
