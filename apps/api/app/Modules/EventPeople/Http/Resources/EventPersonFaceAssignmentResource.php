<?php

namespace App\Modules\EventPeople\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventPersonFaceAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_person_id' => $this->event_person_id,
            'event_media_face_id' => $this->event_media_face_id,
            'source' => $this->source?->value ?? $this->source,
            'confidence' => $this->confidence,
            'status' => $this->status?->value ?? $this->status,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'person' => new EventPersonResource($this->whenLoaded('person')),
        ];
    }
}
