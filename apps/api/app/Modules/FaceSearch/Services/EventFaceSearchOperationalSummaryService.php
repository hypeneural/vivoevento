<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Models\FaceSearchProviderRecord;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\DB;

class EventFaceSearchOperationalSummaryService
{
    /**
     * @return array<string, mixed>
     */
    public function build(EventFaceSearchSetting $settings): array
    {
        $counts = $this->mediaStatusCounts($settings->event_id);
        $backlogCount = $counts['queued_media'] + $counts['processing_media'];
        $collectionReady = is_string($settings->aws_collection_id) && trim($settings->aws_collection_id) !== '';
        $awsPrimary = $settings->enabled
            && $settings->recognition_enabled
            && $settings->search_backend_key === 'aws_rekognition';
        $catalogReady = $collectionReady && $backlogCount === 0;
        $providerCounts = $this->providerCounts($settings);
        $requiresAttention = $counts['failed_media'] > 0;

        return [
            'status' => $this->resolveStatus(
                settings: $settings,
                awsPrimary: $awsPrimary,
                collectionReady: $collectionReady,
                catalogReady: $catalogReady,
                backlogCount: $backlogCount,
            ),
            'search_mode' => $settings->aws_search_mode ?: 'faces',
            'collection_ready' => $collectionReady,
            'catalog_ready' => $catalogReady,
            'is_converging' => $backlogCount > 0,
            'internal_search_ready' => $settings->enabled
                && ($settings->search_backend_key !== 'aws_rekognition' || $collectionReady),
            'guest_search_ready' => $catalogReady && (bool) $settings->allow_public_selfie_search,
            'requires_attention' => $requiresAttention,
            'counts' => [
                ...$counts,
                ...$providerCounts,
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function mediaStatusCounts(int $eventId): array
    {
        /** @var array<string, int> $aggregated */
        $aggregated = EventMedia::query()
            ->where('event_id', $eventId)
            ->selectRaw("COALESCE(face_index_status, '__null__') as face_index_status_key, COUNT(*) as aggregate")
            ->groupBy(DB::raw("COALESCE(face_index_status, '__null__')"))
            ->pluck('aggregate', 'face_index_status_key')
            ->map(fn (mixed $value): int => (int) $value)
            ->all();

        return [
            'total_media' => array_sum($aggregated),
            'queued_media' => $aggregated['queued'] ?? 0,
            'processing_media' => $aggregated['processing'] ?? 0,
            'indexed_media' => $aggregated['indexed'] ?? 0,
            'failed_media' => $aggregated['failed'] ?? 0,
            'skipped_media' => $aggregated['skipped'] ?? 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function providerCounts(EventFaceSearchSetting $settings): array
    {
        $records = FaceSearchProviderRecord::query()
            ->where('event_id', $settings->event_id)
            ->where('backend_key', 'aws_rekognition')
            ->when(
                is_string($settings->aws_collection_id) && trim($settings->aws_collection_id) !== '',
                fn ($query) => $query->where('collection_id', $settings->aws_collection_id),
            );

        return [
            'searchable_records' => (clone $records)
                ->where('searchable', true)
                ->count(),
            'distinct_ready_users' => (clone $records)
                ->where('searchable', true)
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id'),
        ];
    }

    private function resolveStatus(
        EventFaceSearchSetting $settings,
        bool $awsPrimary,
        bool $collectionReady,
        bool $catalogReady,
        int $backlogCount,
    ): string {
        if (! $settings->enabled) {
            return 'disabled';
        }

        if (! $awsPrimary) {
            return 'local_only';
        }

        if (! $collectionReady) {
            return 'provisioning';
        }

        if ($backlogCount > 0) {
            return 'converging';
        }

        if (! $catalogReady || ! $settings->allow_public_selfie_search) {
            return 'ready_for_internal_validation';
        }

        return 'ready_for_guests';
    }
}
