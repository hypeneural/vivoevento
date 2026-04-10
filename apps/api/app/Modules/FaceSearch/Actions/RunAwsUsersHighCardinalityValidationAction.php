<?php

namespace App\Modules\FaceSearch\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventFaceSearchRequest;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\FaceSearch\Models\FaceSearchProviderRecord;
use App\Modules\FaceSearch\Models\FaceSearchQuery;
use App\Modules\FaceSearch\Services\AwsRekognitionFaceSearchBackend;
use App\Modules\FaceSearch\Services\AwsUserHighCardinalityProbeBuilder;
use App\Modules\FaceSearch\Services\AwsUserVectorReadinessService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Throwable;

class RunAwsUsersHighCardinalityValidationAction
{
    public function __construct(
        private readonly RunEventFaceSearchHealthCheckAction $healthCheck,
        private readonly AwsRekognitionFaceSearchBackend $backend,
        private readonly SearchFacesBySelfieAction $searchBySelfie,
        private readonly AwsUserVectorReadinessService $readiness,
        private readonly AwsUserHighCardinalityProbeBuilder $probeBuilder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(
        Event $event,
        int $sampleUsers = 20,
        int $minReadyUsers = 20,
        ?int $targetReadyUsers = 30,
        float $maxFallbackRate = 0.05,
        float $minUsersModeResolutionRate = 0.95,
        float $minTop1MatchRate = 0.85,
        float $minTopKMatchRate = 0.95,
        int $maxP95LatencyMs = 1500,
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
                'skipped_reason' => 'event_not_in_aws_users_mode',
                'current_settings' => $this->settingsSnapshot($settings),
            ];
        }

        if ($this->requiresLocalBaseline($settings) && $settings->provider_key === 'noop') {
            return [
                'status' => 'skipped',
                'event_id' => $event->id,
                'event_title' => $event->title,
                'skipped_reason' => 'local_baseline_provider_noop',
                'message' => 'AWS users validation requires a real local baseline when routing uses local fallback or shadow. Set provider_key=compreface before running this validation.',
                'current_settings' => $this->settingsSnapshot($settings),
            ];
        }

        $healthBefore = $this->healthCheck->execute($event->fresh(['faceSearchSettings']));

        if (($healthBefore['status'] ?? null) !== 'healthy') {
            return [
                'status' => 'skipped',
                'event_id' => $event->id,
                'event_title' => $event->title,
                'skipped_reason' => 'pre_validation_health_not_healthy',
                'current_settings' => $this->settingsSnapshot($settings),
                'health_before' => $healthBefore,
            ];
        }

        $driftBefore = $reconcileBefore
            ? $this->backend->reconcileCollection($event->fresh(['faceSearchSettings']), $settings)
            : null;
        $readiness = $this->readiness->evaluate($event->fresh(['faceSearchSettings']), $settings->fresh());
        $readyUserCount = count((array) ($readiness['ready_clusters'] ?? []));
        $probes = $this->probeBuilder->build(
            event: $event->fresh(['faceSearchSettings']),
            settings: $settings->fresh(),
            limit: min(max(1, $sampleUsers), max(1, $readyUserCount)),
            readinessSummary: $readiness,
        );

        if ($readyUserCount === 0 || $probes === []) {
            return [
                'status' => 'skipped',
                'event_id' => $event->id,
                'event_title' => $event->title,
                'skipped_reason' => $readyUserCount === 0
                    ? 'no_ready_user_vectors'
                    : 'no_eligible_users_probe',
                'current_settings' => $this->settingsSnapshot($settings),
                'health_before' => $healthBefore,
                'drift_before' => $driftBefore,
                'readiness' => $readiness,
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
                    requesterType: 'users_high_cardinality_validation',
                );

                /** @var EventFaceSearchRequest $request */
                $request = $search['request'];
                $queryAudit = $this->latestQueryAuditForRequest($request->id);
                $resultMediaIds = array_values(array_map(
                    static fn (array $result): int => (int) ($result['event_media_id'] ?? 0),
                    (array) ($search['results'] ?? []),
                ));

                $queries[] = [
                    'status' => 'completed',
                    'cluster_id' => (int) ($probe['cluster_id'] ?? 0),
                    'expected_user_id' => (string) ($probe['expected_user_id'] ?? ''),
                    'expected_event_media_ids' => array_values(array_map('intval', (array) ($probe['expected_event_media_ids'] ?? []))),
                    'expected_provider_record_ids' => array_values(array_map('intval', (array) ($probe['expected_provider_record_ids'] ?? []))),
                    'expected_face_ids' => array_values((array) ($probe['expected_face_ids'] ?? [])),
                    'local_face_id' => (int) ($probe['local_face_id'] ?? 0),
                    'event_media_id' => (int) ($probe['event_media_id'] ?? 0),
                    'source_ref' => $probe['source_ref'],
                    'scale_factor' => $probe['scale_factor'],
                    'request_id' => $request->id,
                    'request_status' => $request->status,
                    'result_count' => count((array) ($search['results'] ?? [])),
                    'result_media_ids' => array_values(array_filter($resultMediaIds)),
                    'query_audit' => $queryAudit,
                    'evaluation' => $this->evaluateQuery($probe, $queryAudit, $resultMediaIds),
                ];
            } catch (ValidationException $exception) {
                $queries[] = [
                    'status' => 'blocked_validation',
                    'cluster_id' => (int) ($probe['cluster_id'] ?? 0),
                    'expected_user_id' => (string) ($probe['expected_user_id'] ?? ''),
                    'event_media_id' => (int) ($probe['event_media_id'] ?? 0),
                    'source_ref' => $probe['source_ref'],
                    'scale_factor' => $probe['scale_factor'],
                    'message' => collect($exception->errors())->flatten()->first(),
                ];
            } catch (Throwable $exception) {
                $queries[] = [
                    'status' => 'failed',
                    'cluster_id' => (int) ($probe['cluster_id'] ?? 0),
                    'expected_user_id' => (string) ($probe['expected_user_id'] ?? ''),
                    'event_media_id' => (int) ($probe['event_media_id'] ?? 0),
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
        $metrics = $this->metricsSnapshot($event->id, $queries, $readiness, $driftBefore, $driftAfter, $targetReadyUsers);
        $criteria = [
            'min_ready_users' => max(1, $minReadyUsers),
            'target_ready_users' => $targetReadyUsers !== null && $targetReadyUsers > 0 ? $targetReadyUsers : null,
            'max_fallback_rate' => round($maxFallbackRate, 6),
            'min_users_mode_resolution_rate' => round($minUsersModeResolutionRate, 6),
            'min_top_1_match_rate' => round($minTop1MatchRate, 6),
            'min_top_k_match_rate' => round($minTopKMatchRate, 6),
            'max_p95_latency_ms' => $maxP95LatencyMs,
        ];
        $criteriaEvaluation = $this->criteriaEvaluationSnapshot($metrics, $criteria);

        return [
            'status' => $criteriaEvaluation['passed'] ? 'completed' : 'completed_with_threshold_failures',
            'event_id' => $event->id,
            'event_title' => $event->title,
            'current_settings' => $this->settingsSnapshot($settings->fresh()),
            'health_before' => $healthBefore,
            'health_after' => $healthAfter,
            'drift_before' => $driftBefore,
            'drift_after' => $driftAfter,
            'readiness' => $readiness,
            'queries' => $queries,
            'metrics' => $metrics,
            'criteria' => $criteria,
            'criteria_evaluation' => $criteriaEvaluation,
        ];
    }

    private function isEligible(EventFaceSearchSetting $settings): bool
    {
        return $settings->enabled
            && $settings->recognition_enabled
            && $settings->search_backend_key === 'aws_rekognition'
            && $settings->aws_search_mode === 'users';
    }

    private function requiresLocalBaseline(EventFaceSearchSetting $settings): bool
    {
        return in_array($settings->routing_policy, [
            'aws_primary_local_fallback',
            'aws_primary_local_shadow',
            'local_primary_aws_on_error',
        ], true);
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
                'search_mode_requested' => null,
                'search_mode_resolved' => null,
                'search_mode_fallback_reason' => null,
                'matched_user_ids' => [],
                'top_1_user_id' => null,
                'error_code' => null,
                'error_message' => null,
            ];
        }

        $payload = is_array($query->provider_payload_json) ? $query->provider_payload_json : [];
        $providerResponse = is_array($payload['provider_response'] ?? null) ? $payload['provider_response'] : [];
        $matchedUserIds = collect((array) ($providerResponse['UserMatches'] ?? []))
            ->map(fn (mixed $match): ?string => data_get($match, 'User.UserId'))
            ->filter(fn (?string $userId): bool => is_string($userId) && $userId !== '')
            ->values()
            ->all();

        return [
            'query_id' => $query->id,
            'status' => $query->status?->value ?? (string) $query->status,
            'result_count' => $query->result_count,
            'backend_key' => $query->backend_key,
            'fallback_triggered' => (bool) ($payload['fallback_triggered'] ?? false),
            'response_duration_ms' => isset($payload['response_duration_ms']) ? (int) $payload['response_duration_ms'] : null,
            'search_mode_requested' => is_string($providerResponse['search_mode_requested'] ?? null)
                ? $providerResponse['search_mode_requested']
                : null,
            'search_mode_resolved' => is_string($providerResponse['search_mode_resolved'] ?? null)
                ? $providerResponse['search_mode_resolved']
                : null,
            'search_mode_fallback_reason' => is_string($providerResponse['search_mode_fallback_reason'] ?? null)
                ? $providerResponse['search_mode_fallback_reason']
                : null,
            'matched_user_ids' => $matchedUserIds,
            'top_1_user_id' => $matchedUserIds[0] ?? null,
            'error_code' => $query->error_code,
            'error_message' => $query->error_message,
        ];
    }

    /**
     * @param array<string, mixed> $probe
     * @param array<string, mixed> $queryAudit
     * @param array<int, int> $resultMediaIds
     * @return array<string, mixed>
     */
    private function evaluateQuery(array $probe, array $queryAudit, array $resultMediaIds): array
    {
        $expectedUserId = (string) ($probe['expected_user_id'] ?? '');
        $matchedUserIds = array_values(array_filter((array) ($queryAudit['matched_user_ids'] ?? []), 'is_string'));
        $expectedMediaIds = array_values(array_map('intval', (array) ($probe['expected_event_media_ids'] ?? [])));
        $sharedMediaIds = array_values(array_intersect($expectedMediaIds, array_values(array_filter($resultMediaIds))));

        return [
            'users_mode_resolved' => ($queryAudit['search_mode_resolved'] ?? null) === 'users',
            'top_1_match' => $expectedUserId !== '' && ($queryAudit['top_1_user_id'] ?? null) === $expectedUserId,
            'top_k_match' => $expectedUserId !== '' && in_array($expectedUserId, $matchedUserIds, true),
            'expected_media_seen' => $sharedMediaIds !== [],
            'matched_media_ids' => $sharedMediaIds,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $queries
     * @param array<string, mixed> $readiness
     * @return array<string, mixed>
     */
    private function metricsSnapshot(
        int $eventId,
        array $queries,
        array $readiness,
        ?array $driftBefore,
        ?array $driftAfter,
        ?int $targetReadyUsers,
    ): array {
        $completedQueries = collect($queries)->where('status', 'completed')->values();
        $durations = $completedQueries
            ->map(fn (array $query): ?int => data_get($query, 'query_audit.response_duration_ms'))
            ->filter(fn (?int $duration): bool => $duration !== null)
            ->values();
        $fallbackCount = $completedQueries
            ->filter(fn (array $query): bool => (bool) data_get($query, 'query_audit.fallback_triggered', false))
            ->count();
        $usersModeResolvedCount = $completedQueries
            ->filter(fn (array $query): bool => (bool) data_get($query, 'evaluation.users_mode_resolved', false))
            ->count();
        $top1MatchCount = $completedQueries
            ->filter(fn (array $query): bool => (bool) data_get($query, 'evaluation.top_1_match', false))
            ->count();
        $topKMatchCount = $completedQueries
            ->filter(fn (array $query): bool => (bool) data_get($query, 'evaluation.top_k_match', false))
            ->count();
        $readyUserCount = count((array) ($readiness['ready_clusters'] ?? []));
        $distinctUserIds = FaceSearchProviderRecord::query()
            ->where('event_id', $eventId)
            ->where('backend_key', 'aws_rekognition')
            ->where('searchable', true)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');
        $driftDetectedBefore = ((int) ($driftBefore['remote_only_records_created'] ?? 0) > 0)
            || ((int) ($driftBefore['local_only_records_soft_deleted'] ?? 0) > 0);
        $driftDetectedAfter = ((int) ($driftAfter['remote_only_records_created'] ?? 0) > 0)
            || ((int) ($driftAfter['local_only_records_soft_deleted'] ?? 0) > 0);

        return [
            'queries_attempted' => count($queries),
            'queries_completed' => $completedQueries->count(),
            'queries_blocked_validation' => collect($queries)->where('status', 'blocked_validation')->count(),
            'queries_failed' => collect($queries)->where('status', 'failed')->count(),
            'ready_user_count' => $readyUserCount,
            'target_ready_users' => $targetReadyUsers !== null && $targetReadyUsers > 0 ? $targetReadyUsers : null,
            'target_ready_users_met' => $targetReadyUsers !== null && $targetReadyUsers > 0
                ? $readyUserCount >= $targetReadyUsers
                : null,
            'distinct_synced_user_ids' => $distinctUserIds,
            'users_mode_resolved_count' => $usersModeResolvedCount,
            'users_mode_resolution_rate' => $completedQueries->count() > 0
                ? round($usersModeResolvedCount / $completedQueries->count(), 6)
                : 0.0,
            'fallback_count' => $fallbackCount,
            'fallback_rate' => $completedQueries->count() > 0
                ? round($fallbackCount / $completedQueries->count(), 6)
                : 0.0,
            'top_1_match_count' => $top1MatchCount,
            'top_1_match_rate' => $completedQueries->count() > 0
                ? round($top1MatchCount / $completedQueries->count(), 6)
                : 0.0,
            'top_k_match_count' => $topKMatchCount,
            'top_k_match_rate' => $completedQueries->count() > 0
                ? round($topKMatchCount / $completedQueries->count(), 6)
                : 0.0,
            'avg_response_duration_ms' => $durations->count() > 0 ? round((float) $durations->avg(), 2) : null,
            'p95_response_duration_ms' => $durations->count() > 0
                ? (int) $durations->sort()->values()->get((int) floor(($durations->count() - 1) * 0.95))
                : null,
            'aws_active_records' => FaceSearchProviderRecord::query()
                ->where('event_id', $eventId)
                ->where('backend_key', 'aws_rekognition')
                ->count(),
            'aws_searchable_records' => FaceSearchProviderRecord::query()
                ->where('event_id', $eventId)
                ->where('backend_key', 'aws_rekognition')
                ->where('searchable', true)
                ->count(),
            'aws_user_vector_records' => FaceSearchProviderRecord::query()
                ->where('event_id', $eventId)
                ->where('backend_key', 'aws_rekognition')
                ->where('searchable', true)
                ->whereNotNull('user_id')
                ->count(),
            'local_searchable_faces' => EventMediaFace::query()
                ->where('event_id', $eventId)
                ->where('searchable', true)
                ->count(),
            'drift_detected_before' => $driftDetectedBefore,
            'drift_detected_after' => $driftDetectedAfter,
        ];
    }

    /**
     * @param array<string, mixed> $metrics
     * @param array<string, mixed> $criteria
     * @return array<string, mixed>
     */
    private function criteriaEvaluationSnapshot(array $metrics, array $criteria): array
    {
        $checks = [
            'ready_users' => ((int) ($metrics['ready_user_count'] ?? 0)) >= ((int) ($criteria['min_ready_users'] ?? 1)),
            'fallback_rate' => ((float) ($metrics['fallback_rate'] ?? 1.0)) <= ((float) ($criteria['max_fallback_rate'] ?? 0.0)),
            'users_mode_resolution_rate' => ((float) ($metrics['users_mode_resolution_rate'] ?? 0.0)) >= ((float) ($criteria['min_users_mode_resolution_rate'] ?? 1.0)),
            'top_1_match_rate' => ((float) ($metrics['top_1_match_rate'] ?? 0.0)) >= ((float) ($criteria['min_top_1_match_rate'] ?? 1.0)),
            'top_k_match_rate' => ((float) ($metrics['top_k_match_rate'] ?? 0.0)) >= ((float) ($criteria['min_top_k_match_rate'] ?? 1.0)),
            'p95_latency' => is_numeric($metrics['p95_response_duration_ms'] ?? null)
                && ((int) $metrics['p95_response_duration_ms']) <= ((int) ($criteria['max_p95_latency_ms'] ?? 0)),
            'queries_completed' => ((int) ($metrics['queries_completed'] ?? 0)) > 0,
        ];

        return [
            'checks' => $checks,
            'passed' => ! in_array(false, $checks, true),
        ];
    }
}
