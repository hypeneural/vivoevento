<?php

namespace App\Modules\EventPeople\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventPersonRepresentativeFaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_media_face_id' => $this->event_media_face_id,
            'rank_score' => $this->rank_score,
            'quality_score' => $this->quality_score,
            'pose_bucket' => $this->pose_bucket,
            'context_hash' => $this->context_hash,
            'sync_status' => $this->sync_status?->value ?? $this->sync_status,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'sync_payload' => $this->sync_payload,
            'face' => $this->whenLoaded('face', fn (): array => [
                'id' => $this->face?->id,
                'event_media_id' => $this->face?->event_media_id,
                'face_index' => $this->face?->face_index,
                'quality_score' => $this->face?->quality_score,
                'quality_tier' => $this->face?->quality_tier,
            ]),
        ];
    }
}
