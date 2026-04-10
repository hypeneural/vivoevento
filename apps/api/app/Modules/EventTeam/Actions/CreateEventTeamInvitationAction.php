<?php

namespace App\Modules\EventTeam\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\EventTeam\Models\EventTeamInvitation;
use App\Modules\EventTeam\Support\EventAccessPresetRegistry;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateEventTeamInvitationAction
{
    public function __construct(
        private readonly EventAccessPresetRegistry $presetRegistry,
    ) {}

    public function execute(Event $event, array $data, User $actor): EventTeamInvitation
    {
        return DB::transaction(function () use ($event, $data, $actor) {
            $invitee = (array) $data['invitee'];
            $email = trim((string) ($invitee['email'] ?? ''));
            $phone = (string) $invitee['phone'];
            $presetKey = (string) $data['preset_key'];
            $persistedRole = $this->presetRegistry->persistedRoleForPresetKey($presetKey);
            $existingUser = $this->resolveExistingUser($email, $phone);

            $invitation = EventTeamInvitation::query()->firstOrNew([
                'event_id' => $event->id,
                'invitee_phone' => $phone,
                'preset_key' => $presetKey,
                'status' => EventTeamInvitation::STATUS_PENDING,
            ]);

            if (! $invitation->exists) {
                $invitation->token = Str::random(64);
                $invitation->token_expires_at = now()->addDays(7);
            }

            $invitation->fill([
                'organization_id' => $event->organization_id,
                'invited_by' => $actor->id,
                'existing_user_id' => $existingUser?->id,
                'invitee_name' => (string) $invitee['name'],
                'invitee_email' => $email !== '' ? $email : null,
                'invitee_phone' => $phone,
                'preset_key' => $presetKey,
                'persisted_role' => $persistedRole,
                'delivery_channel' => ($data['send_via_whatsapp'] ?? false) ? 'whatsapp' : 'manual',
                'delivery_status' => ($data['send_via_whatsapp'] ?? false) ? 'pending_dispatch' : 'manual_link',
                'delivery_error' => null,
                'invitation_url' => $this->invitationUrl($invitation->token),
            ]);
            $invitation->save();

            activity()
                ->event('event.team.invitation.created')
                ->performedOn($event)
                ->causedBy($actor)
                ->withProperties([
                    'event_id' => $event->id,
                    'invitation_id' => $invitation->id,
                    'existing_user_id' => $existingUser?->id,
                    'preset_key' => $presetKey,
                    'persisted_role' => $persistedRole,
                ])
                ->log('Convite pendente criado para equipe do evento');

            return $invitation->fresh();
        });
    }

    private function resolveExistingUser(string $email, string $phone): ?User
    {
        if ($email !== '') {
            $user = User::query()->where('email', $email)->first();
            if ($user) {
                return $user;
            }
        }

        return User::query()->where('phone', $phone)->first();
    }

    private function invitationUrl(string $token): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return "{$frontendUrl}/convites/eventos/{$token}";
    }
}
