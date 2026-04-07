<?php

namespace App\Modules\FaceSearch\Http\Resources;

use App\Modules\MediaProcessing\Http\Resources\EventMediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaceSearchMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'rank' => $this['rank'],
            'event_media_id' => $this['event_media_id'],
            'best_distance' => $this['best_distance'],
            'best_quality_tier' => $this['best_quality_tier'] ?? null,
            'best_quality_score' => $this['best_quality_score'],
            'best_face_area_ratio' => $this['best_face_area_ratio'],
            'matched_face_ids' => $this['matched_face_ids'],
            'media' => new EventMediaResource($this['media']),
        ];
    }
}
