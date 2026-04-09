<?php

namespace App\Modules\FaceSearch\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\FaceSearch\Models\FaceSearchProviderRecord;
use App\Modules\FaceSearch\Models\FaceSearchQuery;
use App\Modules\FaceSearch\Services\AwsRekognitionFaceSearchBackend;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Arr;
use Throwable;

class PromoteEventFaceSearchFallbackAction
{
    public function __construct(
        private readonly AwsRekognitionFaceSearchBackend $backend,
        private readonly UpsertEventFaceSearchSettingsAction $upsertSettings,
        private readonly IndexMediaFacesAction $indexMediaFaces,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(
        Event $event,
        bool $syncIndex = false,
        bool $syncReconcile = false,
    ): array {
        $event->loadMissing('faceSearchSettings');

        $settings = $event->faceSearchSettings()->firstOrNew(
            ['event_id' => $event->id],
            EventFaceSearchSetting::defaultAttributes(),
        );

        $previousSettings = $this->settingsSnapshot($settings);

        if (! $this->isEligibleForPromotion($settings)) {
            return [
                'status' => 'skipped',
                'event_id' => $event->id,
                'event_title' => $event->title,
                'skipped_reason' => 'event_not_ready_for_shadow_promotion',
                'previous_settings' => $previousSettings,
                'current_settings' => $previousSettings,
                'observability_before' => $this->observabilitySnapshot($event->id),
            ];
        }

        $preparation = [
            'ensure_backend' => $this->backend->ensureEventBackend($event, $settings),
            'sync_index' => [
                'requested' => $syncIndex,
                'media_count' => 0,
                'faces_detected' => 0,
                'faces_indexed' => 0,
            ],
            'sync_reconcile' => [
                'requested' => $syncReconcile,
                'status' => 'skipped',
            ],
        ];

        if ($syncIndex) {
            $preparation['sync_index'] = $this->syncIndex($event);
        }

        $settings = $event->fresh()->faceSearchSettings()->firstOrNew(
            ['event_id' => $event->id],
            EventFaceSearchSetting::defaultAttributes(),
        );

        if ($syncReconcile) {
            $preparation['sync_reconcile'] = array_merge(
                ['requested' => true, 'status' => 'completed'],
                $this->backend->reconcileCollection($event->fresh(['faceSearchSettings']), $settings),
            );
        }

        $healthPrePromotion = $this->backend->healthCheck($event->fresh(['faceSearchSettings']), $settings);
        $observabilityBefore = $this->observabilitySnapshot($event->id);

        if (($healthPrePromotion['status'] ?? null) !== 'healthy') {
            return [
                'status' => 'skipped',
                'event_id' => $event->id,
                'event_title' => $event->title,
                'skipped_reason' => 'pre_promotion_health_not_healthy',
                'previous_settings' => $previousSettings,
                'current_settings' => $this->settingsSnapshot($settings),
                'preparation' => $preparation,
                'health_pre_promotion' => $healthPrePromotion,
                'observability_before' => $observabilityBefore,
            ];
        }

        $promotedSettings = $this->upsertSettings->execute($event, [
            'fallback_backend_key' => 'local_pgvector',
            'routing_policy' => 'aws_primary_local_fallback',
            'shadow_mode_percentage' => 0,
        ]);

        try {
            $healthPostPromotion = $this->backend->healthCheck(
                $event->fresh(['faceSearchSettings']),
                $promotedSettings->fresh(),
            );
        } catch (Throwable $exception) {
            $restoredSettings = $this->upsertSettings->execute($event, $previousSettings);

            return [
                'status' => 'rolled_back',
                'event_id' => $event->id,
                'event_title' => $event->title,
                'rollback_reason' => 'post_promotion_health_exception',
                'previous_settings' => $previousSettings,
                'current_settings' => $this->settingsSnapshot($restoredSettings),
                'preparation' => $preparation,
                'health_pre_promotion' => $healthPrePromotion,
                'observability_before' => $observabilityBefore,
                'rollback_failure' => [
                    'exception_class' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            ];
        }

        if (($healthPostPromotion['status'] ?? null) !== 'healthy') {
            $restoredSettings = $this->upsertSettings->execute($event, $previousSettings);

            return [
                'status' => 'rolled_back',
                'event_id' => $event->id,
                'event_title' => $event->title,
                'rollback_reason' => 'post_promotion_health_not_healthy',
                'previous_settings' => $previousSettings,
                'current_settings' => $this->settingsSnapshot($restoredSettings),
                'preparation' => $preparation,
                'health_pre_promotion' => $healthPrePromotion,
                'health_post_promotion' => $healthPostPromotion,
                'observability_before' => $observabilityBefore,
            ];
        }

        return [
            'status' => 'promoted',
            'event_id' => $event->id,
            'event_title' => $event->title,
            'previous_settings' => $previousSettings,
            'current_settings' => $this->settingsSnapshot($promotedSettings),
            'preparation' => $preparation,
            'health_pre_promotion' => $healthPrePromotion,
            'health_post_promotion' => $healthPostPromotion,
            'observability_before' => $observabilityBefore,
            'observability_after' => $this->observabilitySnapshot($event->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncIndex(Event $event): array
    {
        $summary = [
            'requested' => true,
            'media_count' => 0,
            'faces_detected' => 0,
            'faces_indexed' => 0,
            'results' => [],
        ];

        EventMedia::query()
            ->where('event_id', $event->id)
            ->where('media_type', 'image')
            ->orderBy('id')
            ->each(function (EventMedia $media) use (&$summary): void {
                $result = $this->indexMediaFaces->execute(
                    $media->fresh(['event.faceSearchSettings', 'variants']),
                );

                $summary['media_count']++;
                $summary['faces_detected'] += (int) ($result['faces_detected'] ?? 0);
                $summary['faces_indexed'] += (int) ($result['faces_indexed'] ?? 0);
                $summary['results'][] = [
                    'event_media_id' => $media->id,
                    'status' => $result['status'] ?? 'unknown',
                    'faces_detected' => (int) ($result['faces_detected'] ?? 0),
                    'faces_indexed' => (int) ($result['faces_indexed'] ?? 0),
                    'skipped_reason' => $result['skipped_reason'] ?? null,
                ];
            });

        return $summary;
    }

    private function isEligibleForPromotion(EventFaceSearchSetting $settings): bool
    {
        return $settings->enabled
            && $settings->recognition_enabled
            && $settings->search_backend_key === 'aws_rekognition'
            && $settings->routing_policy === 'aws_primary_local_shadow';
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsSnapshot(EventFaceSearchSetting $settings): array
    {
        return Arr::only(
            array_replace(EventFaceSearchSetting::defaultAttributes(), $settings->toArray()),
            EventFaceSearchSetting::configurableAttributeKeys(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function observabilitySnapshot(int $eventId): array
    {
        $queries = FaceSearchQuery::query()
            ->where('event_id', $eventId)
            ->get();

        $durations = $queries
            ->map(function (FaceSearchQuery $query): ?float {
                $payload = is_array($query->provider_payload_json) ? $query->provider_payload_json : [];
                $duration = $payload['response_duration_ms'] ?? null;

                return is_numeric($duration) ? (float) $duration : null;
            })
            ->filter(fn (?float $duration): bool => $duration !== null)
            ->values();

        $divergence = $queries
            ->map(function (FaceSearchQuery $query): ?float {
                $payload = is_array($query->provider_payload_json) ? $query->provider_payload_json : [];
                $shadow = $payload['shadow'] ?? null;

                if (! is_array($shadow) || ($shadow['status'] ?? null) !== 'completed') {
                    return null;
                }

                $value = data_get($shadow, 'comparison.divergence_ratio');

                return is_numeric($value) ? (float) $value : null;
            })
            ->filter(fn (?float $value): bool => $value !== null)
            ->values();

        $fallbackCount = $queries
            ->filter(function (FaceSearchQuery $query): bool {
                $payload = is_array($query->provider_payload_json) ? $query->provider_payload_json : [];

                return (bool) ($payload['fallback_triggered'] ?? false);
            })
            ->count();

        $awsRecords = FaceSearchProviderRecord::query()
            ->where('event_id', $eventId)
            ->where('backend_key', 'aws_rekognition')
            ->get();

        $awsRecordsWithTrashed = FaceSearchProviderRecord::query()
            ->withTrashed()
            ->where('event_id', $eventId)
            ->where('backend_key', 'aws_rekognition')
            ->get();

        $unindexedCount = $awsRecords
            ->filter(function (FaceSearchProviderRecord $record): bool {
                $reasons = is_array($record->unindexed_reasons_json) ? $record->unindexed_reasons_json : [];

                return $record->face_id === null && $reasons !== [];
            })
            ->count();

        return [
            'queries_total' => $queries->count(),
            'queries_fallback' => $fallbackCount,
            'queries_fallback_rate' => $queries->count() > 0
                ? round($fallbackCount / $queries->count(), 6)
                : 0.0,
            'queries_avg_response_duration_ms' => $durations->count() > 0
                ? round((float) $durations->avg(), 2)
                : null,
            'queries_avg_shadow_divergence' => $divergence->count() > 0
                ? round((float) $divergence->avg(), 6)
                : null,
            'queries_last_finished_at' => optional($queries->max('finished_at'))?->toIso8601String(),
            'aws_active_records' => $awsRecords->count(),
            'aws_searchable_records' => $awsRecords->where('searchable', true)->count(),
            'aws_unindexed_records' => $unindexedCount,
            'aws_soft_deleted_records' => $awsRecordsWithTrashed->filter(
                fn (FaceSearchProviderRecord $record): bool => $record->trashed()
            )->count(),
            'local_active_faces' => EventMediaFace::query()->where('event_id', $eventId)->count(),
            'local_searchable_faces' => EventMediaFace::query()->where('event_id', $eventId)->where('searchable', true)->count(),
        ];
    }
}
