<?php

namespace App\Modules\EventTeam\Http\Resources;

use App\Modules\EventTeam\Support\EventAccessPresetRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicEventTeamInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $preset = app(EventAccessPresetRegistry::class)->presetByKey((string) $this->preset_key);

        return [
            'id' => $this->id,
            'status' => $this->status,
            'requires_existing_login' => $this->existing_user_id !== null,
            'invitee_name' => $this->invitee_name,
            'invitee_contact' => [
                'email' => $this->invitee_email,
                'phone_masked' => $this->invitee_phone
                    ? \App\Shared\Support\PhoneNumber::mask($this->invitee_phone)
                    : null,
            ],
            'invited_by' => [
                'name' => $this->inviter?->name,
            ],
            'event' => [
                'id' => $this->event?->id,
                'title' => $this->event?->title,
                'date' => $this->event?->starts_at?->toISOString(),
                'status' => $this->event?->status?->value ?? $this->event?->status,
            ],
            'organization' => [
                'id' => $this->event?->organization?->id,
                'name' => $this->event?->organization?->trade_name,
                'slug' => $this->event?->organization?->slug,
            ],
            'access' => [
                'preset_key' => $this->preset_key,
                'role_label' => $preset['label'],
                'description' => $preset['description'],
                'capabilities' => $preset['capabilities'],
            ],
            'next_path' => "/my-events/{$this->event_id}",
            'token_expires_at' => $this->token_expires_at?->toISOString(),
        ];
    }
}
