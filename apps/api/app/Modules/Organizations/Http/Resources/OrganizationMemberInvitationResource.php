<?php

namespace App\Modules\Organizations\Http\Resources;

use App\Modules\Organizations\Support\OrganizationTeamRoleRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationMemberInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $registry = app(OrganizationTeamRoleRegistry::class);

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'status' => $this->status,
            'role_key' => $this->role_key,
            'role_label' => $registry->labelForRoleKey((string) $this->role_key),
            'role_description' => $registry->descriptionForRoleKey((string) $this->role_key),
            'existing_user_id' => $this->existing_user_id,
            'invitee' => [
                'name' => $this->invitee_name,
                'email' => $this->invitee_email,
                'phone' => $this->invitee_phone,
            ],
            'delivery_channel' => $this->delivery_channel,
            'delivery_status' => $this->delivery_status,
            'delivery_error' => $this->delivery_error,
            'invitation_url' => $this->invitation_url,
            'token_expires_at' => $this->token_expires_at?->toISOString(),
            'last_sent_at' => $this->last_sent_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'revoked_at' => $this->revoked_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
