<?php

namespace App\Modules\EventPeople\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventPersonReviewQueueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'queue_key' => $this->queue_key,
            'type' => $this->type?->value ?? $this->type,
            'status' => $this->status?->value ?? $this->status,
            'priority' => $this->priority,
            'event_person_id' => $this->event_person_id,
            'event_media_face_id' => $this->event_media_face_id,
            'payload' => $this->payload,
            'last_signal_at' => $this->last_signal_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'person' => $this->whenLoaded('person', fn () => [
                'id' => $this->person?->id,
                'display_name' => $this->person?->display_name,
                'type' => $this->person?->type?->value ?? $this->person?->type,
                'side' => $this->person?->side?->value ?? $this->person?->side,
            ]),
            'face' => $this->whenLoaded('face', fn () => [
                'id' => $this->face?->id,
                'event_media_id' => $this->face?->event_media_id,
                'face_index' => $this->face?->face_index,
                'bbox' => [
                    'x' => $this->face?->bbox_x,
                    'y' => $this->face?->bbox_y,
                    'w' => $this->face?->bbox_w,
                    'h' => $this->face?->bbox_h,
                ],
            ]),
        ];
    }
}
