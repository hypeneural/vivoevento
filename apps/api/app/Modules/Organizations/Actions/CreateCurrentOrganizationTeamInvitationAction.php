<?php

namespace App\Modules\Organizations\Actions;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMemberInvitation;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateCurrentOrganizationTeamInvitationAction
{
    public function execute(Organization $organization, array $data, User $actor): OrganizationMemberInvitation
    {
        return DB::transaction(function () use ($organization, $data, $actor) {
            $invitee = (array) $data['user'];
            $email = trim((string) ($invitee['email'] ?? ''));
            $phone = (string) $invitee['phone'];
            $roleKey = (string) $data['role_key'];
            $existingUser = $this->resolveExistingUser($email, $phone);

            if ($existingUser && $organization->members()->active()->where('user_id', $existingUser->id)->exists()) {
                throw ValidationException::withMessages([
                    'user.phone' => ['Este contato ja faz parte da equipe desta organizacao.'],
                ]);
            }

            $invitation = OrganizationMemberInvitation::query()->firstOrNew([
                'organization_id' => $organization->id,
                'invitee_phone' => $phone,
                'role_key' => $roleKey,
                'status' => OrganizationMemberInvitation::STATUS_PENDING,
            ]);

            if (! $invitation->exists) {
                $invitation->token = Str::random(64);
                $invitation->token_expires_at = now()->addDays(7);
            }

            $invitation->fill([
                'invited_by' => $actor->id,
                'existing_user_id' => $existingUser?->id,
                'invitee_name' => (string) $invitee['name'],
                'invitee_email' => $email !== '' ? $email : null,
                'invitee_phone' => $phone,
                'role_key' => $roleKey,
                'delivery_channel' => ($data['send_via_whatsapp'] ?? false) ? 'whatsapp' : 'manual',
                'delivery_status' => ($data['send_via_whatsapp'] ?? false) ? 'pending_dispatch' : 'manual_link',
                'delivery_error' => null,
                'invitation_url' => $this->invitationUrl($invitation->token),
            ]);
            $invitation->save();

            activity()
                ->event('organization.team.invitation.created')
                ->performedOn($organization)
                ->causedBy($actor)
                ->withProperties([
                    'organization_id' => $organization->id,
                    'invitation_id' => $invitation->id,
                    'existing_user_id' => $existingUser?->id,
                    'role_key' => $roleKey,
                ])
                ->log('Convite pendente criado para equipe da organizacao');

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

        return "{$frontendUrl}/convites/equipe/{$token}";
    }
}
