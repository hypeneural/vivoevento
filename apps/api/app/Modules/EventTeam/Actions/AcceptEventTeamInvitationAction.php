<?php

namespace App\Modules\EventTeam\Actions;

use App\Modules\Auth\Http\Resources\MeResource;
use App\Modules\EventTeam\Models\EventTeamInvitation;
use App\Modules\EventTeam\Models\EventTeamMember;
use App\Modules\EventTeam\Support\EventAccessPresetRegistry;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AcceptEventTeamInvitationAction
{
    public function __construct(
        private readonly EventAccessPresetRegistry $presetRegistry,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(
        EventTeamInvitation $invitation,
        ?User $authenticatedUser,
        array $data = [],
    ): array {
        return DB::transaction(function () use ($invitation, $authenticatedUser, $data) {
            /** @var EventTeamInvitation $invitation */
            $invitation = EventTeamInvitation::query()
                ->with('event.organization')
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            $resolvedExistingUser = $this->resolveExistingUser($invitation);
            $acceptingUser = $this->resolveAcceptingUser($invitation, $resolvedExistingUser, $authenticatedUser, $data);

            if (! $acceptingUser->roles()->exists()) {
                $acceptingUser->assignRole('viewer');
            }

            EventTeamMember::query()->updateOrCreate(
                [
                    'event_id' => $invitation->event_id,
                    'user_id' => $acceptingUser->id,
                ],
                [
                    'role' => $invitation->persisted_role,
                ],
            );

            $preset = $this->presetRegistry->presetByKey((string) $invitation->preset_key);
            $preferences = (array) ($acceptingUser->preferences ?? []);
            $preferences['active_context'] = [
                'type' => 'event',
                'organization_id' => $invitation->organization_id,
                'event_id' => $invitation->event_id,
                'role_key' => $preset['key'],
                'role_label' => $preset['label'],
                'capabilities' => $preset['capabilities'],
                'entry_path' => "/my-events/{$invitation->event_id}",
            ];

            $acceptingUser->forceFill([
                'preferences' => $preferences,
                'status' => 'active',
                'last_login_at' => now(),
            ])->save();

            $invitation->forceFill([
                'existing_user_id' => $resolvedExistingUser?->id,
                'accepted_user_id' => $acceptingUser->id,
                'accepted_at' => now(),
                'status' => EventTeamInvitation::STATUS_ACCEPTED,
                'delivery_status' => 'accepted',
            ])->save();

            activity()
                ->event('event.team.invitation.accepted')
                ->performedOn($invitation->event)
                ->causedBy($acceptingUser)
                ->withProperties([
                    'event_id' => $invitation->event_id,
                    'invitation_id' => $invitation->id,
                    'user_id' => $acceptingUser->id,
                    'preset_key' => $invitation->preset_key,
                    'persisted_role' => $invitation->persisted_role,
                ])
                ->log('Convite da equipe do evento aceito');

            $token = null;
            if (! $authenticatedUser) {
                $token = $acceptingUser->createToken((string) ($data['device_name'] ?? 'event-invite-web'))->plainTextToken;
            }

            return [
                'accepted' => true,
                'token' => $token,
                'next_path' => "/my-events/{$invitation->event_id}",
                'session' => MeResource::make($acceptingUser->fresh())->resolve(request()),
            ];
        });
    }

    public function resolvePendingInvitationOrFail(string $token): EventTeamInvitation
    {
        $invitation = EventTeamInvitation::query()
            ->with('event.organization')
            ->where('token', $token)
            ->first();

        if (! $invitation) {
            abort(404, 'Convite nao encontrado.');
        }

        if ($invitation->status === EventTeamInvitation::STATUS_ACCEPTED) {
            throw new ConflictHttpException('Este convite ja foi utilizado.');
        }

        if ($invitation->status === EventTeamInvitation::STATUS_REVOKED) {
            abort(410, 'Este convite foi revogado.');
        }

        if ($invitation->token_expires_at && $invitation->token_expires_at->isPast()) {
            abort(410, 'Este convite expirou.');
        }

        return $invitation;
    }

    private function resolveExistingUser(EventTeamInvitation $invitation): ?User
    {
        if ($invitation->existing_user_id) {
            $user = User::query()->find($invitation->existing_user_id);
            if ($user) {
                return $user;
            }
        }

        if (filled($invitation->invitee_email)) {
            $user = User::query()->where('email', $invitation->invitee_email)->first();
            if ($user) {
                return $user;
            }
        }

        return User::query()->where('phone', $invitation->invitee_phone)->first();
    }

    private function resolveAcceptingUser(
        EventTeamInvitation $invitation,
        ?User $resolvedExistingUser,
        ?User $authenticatedUser,
        array $data,
    ): User {
        if ($resolvedExistingUser) {
            if (! $authenticatedUser) {
                throw new ConflictHttpException('Este convite esta vinculado a uma conta existente. Faca login para continuar.');
            }

            if ((int) $authenticatedUser->id !== (int) $resolvedExistingUser->id) {
                throw new AccessDeniedHttpException('Este convite pertence a outro usuario da plataforma.');
            }

            return $authenticatedUser;
        }

        if ($authenticatedUser) {
            if (! $this->matchesInvitationIdentity($authenticatedUser, $invitation)) {
                throw new ConflictHttpException('Este convite esta vinculado a outro contato. Entre com a conta correta.');
            }

            return $authenticatedUser;
        }

        $password = (string) ($data['password'] ?? '');

        if ($password === '') {
            throw ValidationException::withMessages([
                'password' => ['Defina uma senha para ativar sua conta.'],
            ]);
        }

        return User::query()->create([
            'name' => $invitation->invitee_name,
            'email' => filled($invitation->invitee_email)
                ? $invitation->invitee_email
                : "invite+{$invitation->invitee_phone}@eventovivo.local",
            'phone' => $invitation->invitee_phone,
            'password' => Hash::make($password),
            'status' => 'active',
            'remember_token' => Str::random(10),
        ]);
    }

    private function matchesInvitationIdentity(User $user, EventTeamInvitation $invitation): bool
    {
        if (filled($invitation->invitee_email) && strcasecmp((string) $user->email, (string) $invitation->invitee_email) === 0) {
            return true;
        }

        return (string) $user->phone === (string) $invitation->invitee_phone;
    }
}
