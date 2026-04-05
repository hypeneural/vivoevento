<?php

namespace App\Modules\FaceSearch\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventFaceSearchSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'provider_key' => $this->provider_key,
            'embedding_model_key' => $this->embedding_model_key,
            'vector_store_key' => $this->vector_store_key,
            'enabled' => (bool) $this->enabled,
            'min_face_size_px' => $this->min_face_size_px,
            'min_quality_score' => $this->min_quality_score,
            'search_threshold' => $this->search_threshold,
            'top_k' => $this->top_k,
            'allow_public_selfie_search' => (bool) $this->allow_public_selfie_search,
            'selfie_retention_hours' => $this->selfie_retention_hours,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
