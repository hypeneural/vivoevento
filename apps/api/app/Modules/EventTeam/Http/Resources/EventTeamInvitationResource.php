<?php

namespace App\Modules\EventTeam\Http\Resources;

use App\Modules\EventTeam\Support\EventAccessPresetRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventTeamInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $preset = app(EventAccessPresetRegistry::class)->presetByKey((string) $this->preset_key);

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'organization_id' => $this->organization_id,
            'status' => $this->status,
            'preset_key' => $this->preset_key,
            'persisted_role' => $this->persisted_role,
            'role_label' => $preset['label'],
            'capabilities' => $preset['capabilities'],
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
