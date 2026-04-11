<?php

namespace App\Modules\Organizations\Actions;

use App\Modules\Auth\Http\Resources\MeResource;
use App\Modules\Organizations\Enums\OrganizationType;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Organizations\Models\OrganizationMemberInvitation;
use App\Modules\Organizations\Support\OrganizationTeamRoleRegistry;
use App\Modules\Partners\Actions\RebuildPartnerStatsAction;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AcceptOrganizationMemberInvitationAction
{
    public function __construct(
        private readonly OrganizationTeamRoleRegistry $roleRegistry,
        private readonly RebuildPartnerStatsAction $rebuildPartnerStats,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(
        OrganizationMemberInvitation $invitation,
        ?User $authenticatedUser,
        array $data = [],
    ): array {
        return DB::transaction(function () use ($invitation, $authenticatedUser, $data) {
            /** @var OrganizationMemberInvitation $invitation */
            $invitation = OrganizationMemberInvitation::query()
                ->with('organization')
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            $resolvedExistingUser = $this->resolveExistingUser($invitation);
            $acceptingUser = $this->resolveAcceptingUser($invitation, $resolvedExistingUser, $authenticatedUser, $data);

            if (! $acceptingUser->hasRole($invitation->role_key)) {
                $acceptingUser->assignRole($invitation->role_key);
            }

            OrganizationMember::query()->updateOrCreate(
                [
                    'organization_id' => $invitation->organization_id,
                    'user_id' => $acceptingUser->id,
                ],
                [
                    'role_key' => $invitation->role_key,
                    'is_owner' => false,
                    'invited_by' => $invitation->invited_by,
                    'status' => 'active',
                    'invited_at' => $invitation->created_at ?? now(),
                    'joined_at' => now(),
                ],
            );

            $preferences = (array) ($acceptingUser->preferences ?? []);
            $preferences['active_context'] = [
                'type' => 'organization',
                'organization_id' => $invitation->organization_id,
                'event_id' => null,
                'role_key' => $invitation->role_key,
                'role_label' => $this->roleRegistry->labelForRoleKey((string) $invitation->role_key),
                'capabilities' => [],
                'entry_path' => '/',
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
                'status' => OrganizationMemberInvitation::STATUS_ACCEPTED,
                'delivery_status' => 'accepted',
            ])->save();

            if (($invitation->organization->type?->value ?? $invitation->organization->type) === OrganizationType::Partner->value) {
                $this->rebuildPartnerStats->execute($invitation->organization->fresh(['subscriptions.plan']));
            }

            activity()
                ->event('organization.team.invitation.accepted')
                ->performedOn($invitation->organization)
                ->causedBy($acceptingUser)
                ->withProperties([
                    'organization_id' => $invitation->organization_id,
                    'invitation_id' => $invitation->id,
                    'user_id' => $acceptingUser->id,
                    'role_key' => $invitation->role_key,
                ])
                ->log('Convite da equipe da organizacao aceito');

            $token = null;
            if (! $authenticatedUser) {
                $token = $acceptingUser->createToken((string) ($data['device_name'] ?? 'organization-invite-web'))->plainTextToken;
            }

            return [
                'accepted' => true,
                'token' => $token,
                'next_path' => '/',
                'session' => MeResource::make($acceptingUser->fresh())->resolve(request()),
            ];
        });
    }

    public function resolvePendingInvitationOrFail(string $token): OrganizationMemberInvitation
    {
        $invitation = OrganizationMemberInvitation::query()
            ->with('organization')
            ->where('token', $token)
            ->first();

        if (! $invitation) {
            abort(404, 'Convite nao encontrado.');
        }

        if ($invitation->status === OrganizationMemberInvitation::STATUS_ACCEPTED) {
            throw new ConflictHttpException('Este convite ja foi utilizado.');
        }

        if ($invitation->status === OrganizationMemberInvitation::STATUS_REVOKED) {
            abort(410, 'Este convite foi revogado.');
        }

        if ($invitation->token_expires_at && $invitation->token_expires_at->isPast()) {
            abort(410, 'Este convite expirou.');
        }

        return $invitation;
    }

    private function resolveExistingUser(OrganizationMemberInvitation $invitation): ?User
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
        OrganizationMemberInvitation $invitation,
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

    private function matchesInvitationIdentity(User $user, OrganizationMemberInvitation $invitation): bool
    {
        if (filled($invitation->invitee_email) && strcasecmp((string) $user->email, (string) $invitation->invitee_email) === 0) {
            return true;
        }

        return (string) $user->phone === (string) $invitation->invitee_phone;
    }
}
