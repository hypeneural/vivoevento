<?php

namespace App\Modules\EventPeople\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventPersonGroupMembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_person_group_id' => $this->event_person_group_id,
            'event_person_id' => $this->event_person_id,
            'role_label' => $this->role_label,
            'source' => $this->source,
            'confidence' => $this->confidence,
            'status' => $this->status,
            'person' => $this->whenLoaded('person', fn () => [
                'id' => $this->person?->id,
                'display_name' => $this->person?->display_name,
                'type' => $this->person?->type?->value ?? $this->person?->type,
                'side' => $this->person?->side?->value ?? $this->person?->side,
                'status' => $this->person?->status?->value ?? $this->person?->status,
                'has_primary_photo' => (bool) $this->person?->primary_reference_photo_id,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
