<?php

namespace App\Modules\FaceSearch\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\FaceSearch\Models\EventFaceSearchRequest;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Models\FaceSearchProviderRecord;
use App\Modules\FaceSearch\Models\FaceSearchQuery;
use App\Modules\FaceSearch\Services\AwsFallbackSoakProbeBuilder;
use App\Modules\FaceSearch\Services\AwsRekognitionFaceSearchBackend;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Throwable;

class RunAwsFallbackSoakAction
{
    public function __construct(
        private readonly RunEventFaceSearchHealthCheckAction $healthCheck,
        private readonly AwsRekognitionFaceSearchBackend $backend,
        private readonly SearchFacesBySelfieAction $searchBySelfie,
        private readonly AwsFallbackSoakProbeBuilder $probeBuilder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(
        Event $event,
        int $queriesPerEvent = 2,
        bool $reconcileBefore = true,
        bool $reconcileAfter = true,
    ): array {
        $event->loadMissing('faceSearchSettings');

        $settings = $event->faceSearchSettings()->firstOrNew(
            ['event_id' => $event->id],
            EventFaceSearchSetting::defaultAttributes(),
        );

        if (! $this->isEligible($settings)) {
            return [
                'status' => 'skipped',
                'event_id' => $event->id,
                'event_title' => $event->title,
                'skipped_reason' => 'event_not_in_aws_primary_local_fallback',
                'current_settings' => $this->settingsSnapshot($settings),
            ];
        }

        $healthBefore = $this->healthCheck->execute($event->fresh(['faceSearchSettings']));

        if (($healthBefore['status'] ?? null) !== 'healthy') {
            return [
                'status' => 'skipped',
                'event_id' => $event->id,
                'event_title' => $event->title,
                'skipped_reason' => 'pre_soak_health_not_healthy',
                'current_settings' => $this->settingsSnapshot($settings),
                'health_before' => $healthBefore,
            ];
        }

        $driftBefore = $reconcileBefore
            ? $this->backend->reconcileCollection($event->fresh(['faceSearchSettings']), $settings)
            : null;

        $probes = $this->probeBuilder->build(
            event: $event->fresh(['faceSearchSettings']),
            settings: $settings,
            limit: max(1, $queriesPerEvent),
        );

        if ($probes === []) {
            return [
                'status' => 'skipped',
                'event_id' => $event->id,
                'event_title' => $event->title,
                'skipped_reason' => 'no_eligible_selfie_probe',
                'current_settings' => $this->settingsSnapshot($settings),
                'health_before' => $healthBefore,
                'drift_before' => $driftBefore,
            ];
        }

        $queries = [];

        foreach ($probes as $probe) {
            try {
                $search = $this->searchBySelfie->execute(
                    event: $event->fresh(['faceSearchSettings']),
                    selfie: new UploadedFile(
                        path: (string) $probe['probe_path'],
                        originalName: basename((string) $probe['probe_path']),
                        test: true,
                    ),
                    requesterType: 'soak_command',
                );

                /** @var EventFaceSearchRequest $request */
                $request = $search['request'];

                $queries[] = [
                    'status' => 'completed',
                    'event_media_id' => $probe['event_media_id'],
                    'source_ref' => $probe['source_ref'],
                    'scale_factor' => $probe['scale_factor'],
                    'request_id' => $request->id,
                    'request_status' => $request->status,
                    'result_count' => count($search['results']),
                    'query_audit' => $this->latestQueryAuditForRequest($request->id),
                ];
            } catch (ValidationException $exception) {
                $queries[] = [
                    'status' => 'blocked_validation',
                    'event_media_id' => $probe['event_media_id'],
                    'source_ref' => $probe['source_ref'],
                    'scale_factor' => $probe['scale_factor'],
                    'message' => collect($exception->errors())->flatten()->first(),
                ];
            } catch (Throwable $exception) {
                $queries[] = [
                    'status' => 'failed',
                    'event_media_id' => $probe['event_media_id'],
                    'source_ref' => $probe['source_ref'],
                    'scale_factor' => $probe['scale_factor'],
                    'exception_class' => $exception::class,
                    'message' => $exception->getMessage(),
                ];
            } finally {
                $probePath = (string) ($probe['probe_path'] ?? '');

                if ($probePath !== '' && File::exists($probePath)) {
                    File::delete($probePath);
                }
            }
        }

        $driftAfter = $reconcileAfter
            ? $this->backend->reconcileCollection($event->fresh(['faceSearchSettings']), $settings->fresh())
            : null;
        $healthAfter = $this->healthCheck->execute($event->fresh(['faceSearchSettings']));

        return [
            'status' => 'completed',
            'event_id' => $event->id,
            'event_title' => $event->title,
            'current_settings' => $this->settingsSnapshot($settings->fresh()),
            'health_before' => $healthBefore,
            'health_after' => $healthAfter,
            'drift_before' => $driftBefore,
            'drift_after' => $driftAfter,
            'queries' => $queries,
            'metrics' => $this->metricsSnapshot($event->id, $queries, $driftBefore, $driftAfter),
        ];
    }

    private function isEligible(EventFaceSearchSetting $settings): bool
    {
        return $settings->enabled
            && $settings->recognition_enabled
            && $settings->search_backend_key === 'aws_rekognition'
            && $settings->routing_policy === 'aws_primary_local_fallback';
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsSnapshot(EventFaceSearchSetting $settings): array
    {
        return \Illuminate\Support\Arr::only(
            array_replace(EventFaceSearchSetting::defaultAttributes(), $settings->toArray()),
            EventFaceSearchSetting::configurableAttributeKeys(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function latestQueryAuditForRequest(int $requestId): array
    {
        $query = FaceSearchQuery::query()
            ->where('event_face_search_request_id', $requestId)
            ->latest('id')
            ->first();

        if (! $query) {
            return [
                'query_id' => null,
                'status' => 'missing',
                'result_count' => 0,
                'backend_key' => null,
                'fallback_triggered' => false,
                'response_duration_ms' => null,
                'error_code' => null,
                'error_message' => null,
            ];
        }

        $payload = is_array($query->provider_payload_json) ? $query->provider_payload_json : [];

        return [
            'query_id' => $query->id,
            'status' => $query->status?->value ?? (string) $query->status,
            'result_count' => $query->result_count,
            'backend_key' => $query->backend_key,
            'fallback_triggered' => (bool) ($payload['fallback_triggered'] ?? false),
            'response_duration_ms' => isset($payload['response_duration_ms']) ? (int) $payload['response_duration_ms'] : null,
            'error_code' => $query->error_code,
            'error_message' => $query->error_message,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $queries
     * @return array<string, mixed>
     */
    private function metricsSnapshot(
        int $eventId,
        array $queries,
        ?array $driftBefore,
        ?array $driftAfter,
    ): array {
        $durations = collect($queries)
            ->map(fn (array $query): ?int => data_get($query, 'query_audit.response_duration_ms'))
            ->filter(fn (?int $duration): bool => $duration !== null)
            ->values();

        $fallbackCount = collect($queries)
            ->filter(fn (array $query): bool => (bool) data_get($query, 'query_audit.fallback_triggered', false))
            ->count();

        $driftDetectedBefore = ((int) ($driftBefore['remote_only_records_created'] ?? 0) > 0)
            || ((int) ($driftBefore['local_only_records_soft_deleted'] ?? 0) > 0);
        $driftDetectedAfter = ((int) ($driftAfter['remote_only_records_created'] ?? 0) > 0)
            || ((int) ($driftAfter['local_only_records_soft_deleted'] ?? 0) > 0);

        return [
            'queries_attempted' => count($queries),
            'queries_completed' => collect($queries)->where('status', 'completed')->count(),
            'queries_blocked_validation' => collect($queries)->where('status', 'blocked_validation')->count(),
            'queries_failed' => collect($queries)->where('status', 'failed')->count(),
            'fallback_count' => $fallbackCount,
            'fallback_rate' => count($queries) > 0 ? round($fallbackCount / count($queries), 6) : 0.0,
            'avg_response_duration_ms' => $durations->count() > 0 ? round((float) $durations->avg(), 2) : null,
            'p95_response_duration_ms' => $durations->count() > 0
                ? (int) $durations->sort()->values()->get((int) floor(($durations->count() - 1) * 0.95))
                : null,
            'face_search_queries_total_after' => FaceSearchQuery::query()->where('event_id', $eventId)->count(),
            'aws_active_records' => FaceSearchProviderRecord::query()
                ->where('event_id', $eventId)
                ->where('backend_key', 'aws_rekognition')
                ->count(),
            'aws_searchable_records' => FaceSearchProviderRecord::query()
                ->where('event_id', $eventId)
                ->where('backend_key', 'aws_rekognition')
                ->where('searchable', true)
                ->count(),
            'local_searchable_faces' => EventMediaFace::query()
                ->where('event_id', $eventId)
                ->where('searchable', true)
                ->count(),
            'drift_detected_before' => $driftDetectedBefore,
            'drift_detected_after' => $driftDetectedAfter,
        ];
    }
}
