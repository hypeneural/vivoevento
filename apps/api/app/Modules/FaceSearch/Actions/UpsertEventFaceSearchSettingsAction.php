<?php

namespace App\Modules\FaceSearch\Actions;

use App\Modules\Events\Models\Event;
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

        $attributes = array_replace(
            EventFaceSearchSetting::defaultAttributes(),
            $existing?->only([
                'provider_key',
                'embedding_model_key',
                'vector_store_key',
                'enabled',
                'min_face_size_px',
                'min_quality_score',
                'search_threshold',
                'top_k',
                'allow_public_selfie_search',
                'selfie_retention_hours',
            ]) ?? [],
            Arr::only($payload, [
                'provider_key',
                'embedding_model_key',
                'vector_store_key',
                'enabled',
                'min_face_size_px',
                'min_quality_score',
                'search_threshold',
                'top_k',
                'allow_public_selfie_search',
                'selfie_retention_hours',
            ]),
        );

        return EventFaceSearchSetting::query()->updateOrCreate(
            [
                'event_id' => $event->id,
            ],
            [
                'provider_key' => (string) ($attributes['provider_key'] ?? 'noop'),
                'embedding_model_key' => (string) ($attributes['embedding_model_key'] ?? config('face_search.default_embedding_model', 'face-embedding-foundation-v1')),
                'vector_store_key' => (string) ($attributes['vector_store_key'] ?? 'pgvector'),
                'enabled' => (bool) ($attributes['enabled'] ?? false),
                'min_face_size_px' => (int) ($attributes['min_face_size_px'] ?? config('face_search.min_face_size_px', 96)),
                'min_quality_score' => (float) ($attributes['min_quality_score'] ?? config('face_search.min_quality_score', 0.60)),
                'search_threshold' => (float) ($attributes['search_threshold'] ?? config('face_search.search_threshold', 0.35)),
                'top_k' => (int) ($attributes['top_k'] ?? config('face_search.top_k', 50)),
                'allow_public_selfie_search' => (bool) ($attributes['allow_public_selfie_search'] ?? false),
                'selfie_retention_hours' => (int) ($attributes['selfie_retention_hours'] ?? 24),
            ],
        );
    }
}
