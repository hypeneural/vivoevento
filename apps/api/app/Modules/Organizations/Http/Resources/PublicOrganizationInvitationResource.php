<?php

namespace App\Modules\Organizations\Http\Resources;

use App\Modules\Organizations\Support\OrganizationTeamRoleRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PublicOrganizationInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $registry = app(OrganizationTeamRoleRegistry::class);

        return [
            'id' => $this->id,
            'organization' => [
                'id' => $this->organization_id,
                'name' => $this->organization?->displayName(),
                'slug' => $this->organization?->slug,
                'logo_url' => $this->organization?->logo_path
                    ? Storage::disk('public')->url($this->organization->logo_path)
                    : null,
            ],
            'invitee_name' => $this->invitee_name,
            'invitee_contact' => [
                'email' => $this->invitee_email,
                'phone_masked' => $this->maskPhone($this->invitee_phone),
            ],
            'access' => [
                'role_key' => $this->role_key,
                'role_label' => $registry->labelForRoleKey((string) $this->role_key),
                'description' => $registry->descriptionForRoleKey((string) $this->role_key),
            ],
            'requires_existing_login' => $this->existing_user_id !== null,
            'token_expires_at' => $this->token_expires_at?->toISOString(),
            'invitation_url' => $this->invitation_url,
        ];
    }

    private function maskPhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) < 6) {
            return $phone;
        }

        return sprintf('+%s ******%s', substr($digits, 0, 2), substr($digits, -4));
    }
}
