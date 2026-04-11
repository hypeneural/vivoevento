<?php

namespace App\Modules\EventPeople\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventPersonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'display_name' => $this->display_name,
            'slug' => $this->slug,
            'type' => $this->type?->value ?? $this->type,
            'side' => $this->side?->value ?? $this->side,
            'avatar_media_id' => $this->avatar_media_id,
            'avatar_face_id' => $this->avatar_face_id,
            'importance_rank' => $this->importance_rank,
            'notes' => $this->notes,
            'status' => $this->status?->value ?? $this->status,
            'stats' => $this->whenLoaded('mediaStats', fn () => EventPersonMediaStatResource::collection($this->mediaStats)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
