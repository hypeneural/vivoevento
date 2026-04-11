<?php

namespace App\Modules\EventPeople\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventMediaFacePeopleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_media_id' => $this->event_media_id,
            'face_index' => $this->face_index,
            'bbox' => [
                'x' => $this->bbox_x,
                'y' => $this->bbox_y,
                'w' => $this->bbox_w,
                'h' => $this->bbox_h,
            ],
            'quality' => [
                'score' => $this->quality_score,
                'tier' => $this->quality_tier,
                'rejection_reason' => $this->quality_rejection_reason,
            ],
            'assignments' => EventPersonFaceAssignmentResource::collection(
                $this->whenLoaded('personAssignments'),
            ),
        ];
    }
}
