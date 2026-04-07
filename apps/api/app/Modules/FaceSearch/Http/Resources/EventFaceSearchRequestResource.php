<?php

namespace App\Modules\FaceSearch\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventFaceSearchRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'requester_type' => $this->requester_type,
            'requester_user_id' => $this->requester_user_id,
            'status' => $this->status,
            'consent_version' => $this->consent_version,
            'selfie_storage_strategy' => $this->selfie_storage_strategy,
            'faces_detected' => $this->faces_detected,
            'query_face_quality_score' => $this->query_face_quality_score,
            'query_face_quality_tier' => $this->query_face_quality_tier,
            'query_face_rejection_reason' => $this->query_face_rejection_reason,
            'top_k' => $this->top_k,
            'best_distance' => $this->best_distance,
            'result_photo_ids' => $this->result_photo_ids_json ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
