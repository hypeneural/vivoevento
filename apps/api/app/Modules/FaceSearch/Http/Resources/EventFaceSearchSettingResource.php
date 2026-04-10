<?php

namespace App\Modules\FaceSearch\Http\Resources;

use App\Modules\FaceSearch\Services\EventFaceSearchOperationalSummaryService;
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
            'search_strategy' => $this->search_strategy,
            'enabled' => (bool) $this->enabled,
            'min_face_size_px' => $this->min_face_size_px,
            'min_quality_score' => $this->min_quality_score,
            'search_threshold' => $this->search_threshold,
            'top_k' => $this->top_k,
            'allow_public_selfie_search' => (bool) $this->allow_public_selfie_search,
            'selfie_retention_hours' => $this->selfie_retention_hours,
            'recognition_enabled' => (bool) $this->recognition_enabled,
            'search_backend_key' => $this->search_backend_key,
            'fallback_backend_key' => $this->fallback_backend_key,
            'routing_policy' => $this->routing_policy,
            'shadow_mode_percentage' => $this->shadow_mode_percentage,
            'aws_region' => $this->aws_region,
            'aws_collection_id' => $this->aws_collection_id,
            'aws_collection_arn' => $this->aws_collection_arn,
            'aws_face_model_version' => $this->aws_face_model_version,
            'aws_search_mode' => $this->aws_search_mode,
            'aws_index_quality_filter' => $this->aws_index_quality_filter,
            'aws_search_faces_quality_filter' => $this->aws_search_faces_quality_filter,
            'aws_search_users_quality_filter' => $this->aws_search_users_quality_filter,
            'aws_search_face_match_threshold' => $this->aws_search_face_match_threshold,
            'aws_search_user_match_threshold' => $this->aws_search_user_match_threshold,
            'aws_associate_user_match_threshold' => $this->aws_associate_user_match_threshold,
            'aws_max_faces_per_image' => $this->aws_max_faces_per_image,
            'aws_index_profile_key' => $this->aws_index_profile_key,
            'aws_detection_attributes_json' => $this->aws_detection_attributes_json,
            'delete_remote_vectors_on_event_close' => (bool) $this->delete_remote_vectors_on_event_close,
            'operational_summary' => app(EventFaceSearchOperationalSummaryService::class)->build($this->resource),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
