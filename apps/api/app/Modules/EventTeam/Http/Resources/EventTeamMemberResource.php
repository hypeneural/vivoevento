<?php

namespace App\Modules\EventTeam\Http\Resources;

use App\Modules\EventTeam\Support\EventAccessPresetRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventTeamMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $preset = app(EventAccessPresetRegistry::class)->presetForPersistedRole((string) $this->role);

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'role' => $this->role,
            'role_key' => $preset['key'],
            'role_label' => $preset['label'],
            'capabilities' => $preset['capabilities'],
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'avatar_path' => $this->user->avatar_path,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
