<?php

namespace App\Modules\FaceSearch\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Jobs\EnsureAwsCollectionJob;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use Illuminate\Support\Arr;

class UpsertEventFaceSearchSettingsAction
{
    /**
     * @param array<string, mixed> $payload
     */
    public function execute(Event $event, array $payload): EventFaceSearchSetting
    {
        $existing = EventFaceSearchSetting::query()->where('event_id', $event->id)->first();
        $configurableKeys = EventFaceSearchSetting::configurableAttributeKeys();

        $attributes = array_replace(
            EventFaceSearchSetting::defaultAttributes(),
            $existing?->only($configurableKeys) ?? [],
            Arr::only($payload, $configurableKeys),
        );

        $settings = EventFaceSearchSetting::query()->updateOrCreate(
            [
                'event_id' => $event->id,
            ],
            Arr::only([
                'provider_key' => (string) ($attributes['provider_key'] ?? 'noop'),
                'embedding_model_key' => (string) ($attributes['embedding_model_key'] ?? config('face_search.default_embedding_model', 'face-embedding-foundation-v1')),
                'vector_store_key' => (string) ($attributes['vector_store_key'] ?? 'pgvector'),
                'search_strategy' => (string) ($attributes['search_strategy'] ?? config('face_search.default_search_strategy', 'exact')),
                'enabled' => (bool) ($attributes['enabled'] ?? false),
                'min_face_size_px' => (int) ($attributes['min_face_size_px'] ?? config('face_search.min_face_size_px', 24)),
                'min_quality_score' => (float) ($attributes['min_quality_score'] ?? config('face_search.min_quality_score', 0.60)),
                'search_threshold' => (float) ($attributes['search_threshold'] ?? config('face_search.search_threshold', 0.50)),
                'top_k' => (int) ($attributes['top_k'] ?? config('face_search.top_k', 50)),
                'allow_public_selfie_search' => (bool) ($attributes['allow_public_selfie_search'] ?? false),
                'selfie_retention_hours' => (int) ($attributes['selfie_retention_hours'] ?? 24),
                'recognition_enabled' => (bool) ($attributes['recognition_enabled'] ?? false),
                'search_backend_key' => (string) ($attributes['search_backend_key'] ?? 'local_pgvector'),
                'fallback_backend_key' => $attributes['fallback_backend_key'] !== null
                    ? (string) $attributes['fallback_backend_key']
                    : null,
                'routing_policy' => (string) ($attributes['routing_policy'] ?? 'local_only'),
                'shadow_mode_percentage' => (int) ($attributes['shadow_mode_percentage'] ?? 0),
                'aws_region' => (string) ($attributes['aws_region'] ?? config('face_search.providers.aws_rekognition.region', 'eu-central-1')),
                'aws_collection_id' => $attributes['aws_collection_id'] !== null
                    ? (string) $attributes['aws_collection_id']
                    : null,
                'aws_collection_arn' => $attributes['aws_collection_arn'] !== null
                    ? (string) $attributes['aws_collection_arn']
                    : null,
                'aws_face_model_version' => $attributes['aws_face_model_version'] !== null
                    ? (string) $attributes['aws_face_model_version']
                    : null,
                'aws_search_mode' => (string) ($attributes['aws_search_mode'] ?? 'faces'),
                'aws_index_quality_filter' => (string) ($attributes['aws_index_quality_filter'] ?? config('face_search.providers.aws_rekognition.index_quality_filter', 'AUTO')),
                'aws_search_faces_quality_filter' => (string) ($attributes['aws_search_faces_quality_filter'] ?? config('face_search.providers.aws_rekognition.search_faces_quality_filter', 'NONE')),
                'aws_search_users_quality_filter' => (string) ($attributes['aws_search_users_quality_filter'] ?? config('face_search.providers.aws_rekognition.search_users_quality_filter', 'NONE')),
                'aws_search_face_match_threshold' => (float) ($attributes['aws_search_face_match_threshold'] ?? config('face_search.providers.aws_rekognition.search_face_match_threshold', 80)),
                'aws_search_user_match_threshold' => (float) ($attributes['aws_search_user_match_threshold'] ?? config('face_search.providers.aws_rekognition.search_user_match_threshold', 80)),
                'aws_associate_user_match_threshold' => (float) ($attributes['aws_associate_user_match_threshold'] ?? config('face_search.providers.aws_rekognition.associate_user_match_threshold', 75)),
                'aws_max_faces_per_image' => (int) ($attributes['aws_max_faces_per_image'] ?? 100),
                'aws_index_profile_key' => (string) ($attributes['aws_index_profile_key'] ?? 'social_gallery_event'),
                'aws_detection_attributes_json' => array_values($attributes['aws_detection_attributes_json'] ?? ['DEFAULT', 'FACE_OCCLUDED']),
                'delete_remote_vectors_on_event_close' => (bool) ($attributes['delete_remote_vectors_on_event_close'] ?? false),
            ], array_merge(['event_id'], $configurableKeys)),
        );

        if ($settings->enabled && $settings->recognition_enabled && $settings->search_backend_key === 'aws_rekognition') {
            EnsureAwsCollectionJob::dispatch($event->id);
        }

        return $settings;
    }
}
