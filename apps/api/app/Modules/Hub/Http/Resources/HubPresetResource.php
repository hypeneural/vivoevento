<?php

namespace App\Modules\Hub\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HubPresetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = is_array($this->preset_payload_json) ? $this->preset_payload_json : [];

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'description' => $this->description,
            'theme_key' => $this->theme_key,
            'layout_key' => $this->layout_key,
            'source_event' => $this->sourceEvent
                ? [
                    'id' => $this->sourceEvent->id,
                    'title' => $this->sourceEvent->title,
                    'slug' => $this->sourceEvent->slug,
                ]
                : null,
            'creator' => $this->creator
                ? [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ]
                : null,
            'payload' => [
                'button_style' => $payload['button_style'] ?? null,
                'builder_config' => $payload['builder_config'] ?? null,
                'buttons' => $payload['buttons'] ?? [],
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
