<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceSearchMatchData;
use App\Modules\FaceSearch\Enums\FaceSearchQueryStatus;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class FaceSearchRouter
{
    /**
     * @param iterable<int, FaceSearchBackendInterface> $backends
     */
    public function __construct(
        iterable $backends,
        private readonly ?FaceSearchFailureClassifier $failureClassifier = null,
        private readonly ?FaceSearchMediaSourceLoader $sourceLoader = null,
    ) {
        $this->backends = collect($backends)
            ->keyBy(fn (FaceSearchBackendInterface $backend) => $backend->key())
            ->all();
    }

    /**
     * @var array<string, FaceSearchBackendInterface>
     */
    private array $backends;

    public function backendForSettings(EventFaceSearchSetting $settings): FaceSearchBackendInterface
    {
        return $this->backendByKey($this->primaryBackendKeyForSettings($settings));
    }

    /**
     * @return array{
     *   matches:array<int, FaceSearchMatchData>,
     *   provider_payload_json?:array<string, mixed>,
     *   query_status:string,
     *   primary_backend_key:string,
     *   response_backend_key:string,
      *   fallback_backend_key:string|null,
      *   fallback_triggered:bool,
     *   primary_duration_ms:int,
     *   response_duration_ms:int,
      *   primary_failure?:array<string, mixed>|null,
      *   shadow?:array<string, mixed>|null
     * }
     */
    public function executeSelfieSearch(
        Event $event,
        EventFaceSearchSetting $settings,
        EventMedia $probeMedia,
        string $binary,
        DetectedFaceData $face,
        int $topK,
    ): array {
        $primary = $this->backendForSettings($settings);
        $fallback = $this->fallbackBackendForSettings($settings, $primary->key());
        $shadow = $this->shadowBackendForSettings($settings, $primary->key(), $fallback?->key());

        $fallbackTriggered = false;
        $primaryFailure = null;
        $primaryDurationMs = 0;

        $primaryStartedAt = microtime(true);
        try {
            $response = $this->executeBackendSearch($primary, $event, $settings, $probeMedia, $binary, $face, $topK);
            $responseBackendKey = $primary->key();
            $primaryDurationMs = (int) round((microtime(true) - $primaryStartedAt) * 1000);
        } catch (Throwable $exception) {
            $primaryFailure = $this->failureClassifier()->classify($exception) + [
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ];
            $primaryDurationMs = (int) round((microtime(true) - $primaryStartedAt) * 1000);

            if ($fallback === null || ! $this->failureClassifier()->isRetryable($exception)) {
                throw $exception;
            }

            $fallbackStartedAt = microtime(true);
            $response = $this->executeBackendSearch($fallback, $event, $settings, $probeMedia, $binary, $face, $topK);
            $response['duration_ms'] = (int) round((microtime(true) - $fallbackStartedAt) * 1000);
            $responseBackendKey = $fallback->key();
            $fallbackTriggered = true;
        }

        if (! isset($response['duration_ms'])) {
            $response['duration_ms'] = $primaryDurationMs;
        }

        return [
            'matches' => $response['matches'] ?? [],
            'provider_payload_json' => $response['provider_payload_json'] ?? null,
            'query_status' => $fallbackTriggered ? FaceSearchQueryStatus::Degraded->value : FaceSearchQueryStatus::Completed->value,
            'primary_backend_key' => $primary->key(),
            'response_backend_key' => $responseBackendKey,
            'fallback_backend_key' => $fallback?->key(),
            'fallback_triggered' => $fallbackTriggered,
            'primary_duration_ms' => $primaryDurationMs,
            'response_duration_ms' => (int) ($response['duration_ms'] ?? 0),
            'primary_failure' => $primaryFailure,
            'shadow' => $shadow !== null
                ? $this->executeShadowSearch(
                    $shadow,
                    $event,
                    $settings,
                    $probeMedia,
                    $binary,
                    $face,
                    $topK,
                    $response['matches'] ?? [],
                )
                : null,
        ];
    }

    /**
     * @return array{
     *   status:string,
     *   source_ref:string|null,
     *   faces_detected:int,
     *   faces_indexed:int,
     *   skipped_reason:string|null,
     *   quality_summary?:array<string,int>,
     *   dominant_rejection_reason?:string|null
     * }
     */
    public function indexMedia(EventMedia $media, EventFaceSearchSetting $settings): array
    {
        $primary = $this->backendForSettings($settings);
        $result = $primary->indexMedia($media, $settings);
        $shadow = $this->shadowIndexBackendForSettings($settings, $primary->key());

        if ($shadow === null) {
            return $result;
        }

        $this->ensureShadowBaselineSource($media);

        $startedAt = microtime(true);

        try {
            $shadowResult = $shadow->indexMedia($media, $settings);
            $primaryGateAlignment = $this->alignLocalShadowBaselineWithPrimaryGate(
                media: $media,
                primary: $primary,
                shadow: $shadow,
                primaryResult: $result,
            );

            $result['shadow'] = [
                'backend_key' => $shadow->key(),
                'status' => 'completed',
                'baseline_required' => true,
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'result' => $shadowResult,
                'primary_gate_alignment' => $primaryGateAlignment,
            ];

            return $result;
        } catch (Throwable $exception) {
            $result['shadow'] = [
                'backend_key' => $shadow->key(),
                'status' => 'failed',
                'baseline_required' => true,
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'failure' => $this->failureClassifier()->classify($exception) + [
                    'exception_class' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            ];

            throw $exception;
        }
    }

    private function primaryBackendKeyForSettings(EventFaceSearchSetting $settings): string
    {
        return match ($settings->routing_policy ?: 'local_only') {
            'aws_primary_local_fallback',
            'aws_primary_local_shadow' => $this->normalizeBackendKey(
                $settings->recognition_enabled ? 'aws_rekognition' : 'local_pgvector',
            ),
            'local_primary_aws_on_error' => $this->normalizeBackendKey('local_pgvector'),
            'local_only' => $this->normalizeBackendKey(
                ($settings->search_backend_key === 'aws_rekognition' && ! $settings->recognition_enabled)
                    ? 'local_pgvector'
                    : ($settings->search_backend_key ?: 'local_pgvector'),
            ),
            default => $this->normalizeBackendKey($settings->search_backend_key ?: 'local_pgvector'),
        };
    }

    private function fallbackBackendForSettings(
        EventFaceSearchSetting $settings,
        string $primaryBackendKey,
    ): ?FaceSearchBackendInterface {
        $fallbackKey = match ($settings->routing_policy ?: 'local_only') {
            'aws_primary_local_fallback' => 'local_pgvector',
            'local_primary_aws_on_error' => $settings->recognition_enabled ? 'aws_rekognition' : null,
            default => $settings->fallback_backend_key,
        };

        if (! is_string($fallbackKey) || $fallbackKey === '' || $fallbackKey === $primaryBackendKey) {
            return null;
        }

        return $this->backendByKey($fallbackKey, false);
    }

    private function shadowBackendForSettings(
        EventFaceSearchSetting $settings,
        string $primaryBackendKey,
        ?string $fallbackBackendKey,
    ): ?FaceSearchBackendInterface {
        if (($settings->routing_policy ?: 'local_only') !== 'aws_primary_local_shadow') {
            return null;
        }

        if ((int) $settings->shadow_mode_percentage <= 0 || ! $this->shouldRunShadow($settings)) {
            return null;
        }

        $shadowKey = is_string($fallbackBackendKey) && $fallbackBackendKey !== ''
            ? $fallbackBackendKey
            : 'local_pgvector';

        if ($shadowKey === $primaryBackendKey) {
            return null;
        }

        return $this->backendByKey($shadowKey, false);
    }

    private function shadowIndexBackendForSettings(
        EventFaceSearchSetting $settings,
        string $primaryBackendKey,
    ): ?FaceSearchBackendInterface {
        if (($settings->routing_policy ?: 'local_only') !== 'aws_primary_local_shadow') {
            return null;
        }

        $shadowKey = $this->normalizeBackendKey($settings->fallback_backend_key ?: 'local_pgvector');

        if ($shadowKey === $primaryBackendKey) {
            return null;
        }

        return $this->backendByKey($shadowKey, false);
    }

    private function shouldRunShadow(EventFaceSearchSetting $settings): bool
    {
        $percentage = max(0, min(100, (int) $settings->shadow_mode_percentage));

        if ($percentage === 0) {
            return false;
        }

        if ($percentage === 100) {
            return true;
        }

        return random_int(1, 100) <= $percentage;
    }

    /**
     * @return array{
     *   backend_key:string,
     *   status:string,
      *   result_count:int,
     *   latency_ms:int,
     *   comparison?:array<string, mixed>,
      *   provider_payload_json?:array<string, mixed>|null,
      *   failure?:array<string, mixed>
     * }
     */
    private function executeShadowSearch(
        FaceSearchBackendInterface $backend,
        Event $event,
        EventFaceSearchSetting $settings,
        EventMedia $probeMedia,
        string $binary,
        DetectedFaceData $face,
        int $topK,
        array $primaryMatches,
    ): array {
        $startedAt = microtime(true);

        try {
            $result = $this->executeBackendSearch($backend, $event, $settings, $probeMedia, $binary, $face, $topK);

            return [
                'backend_key' => $backend->key(),
                'status' => 'completed',
                'result_count' => count($result['matches'] ?? []),
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'comparison' => $this->compareShadowMatches($primaryMatches, $result['matches'] ?? []),
                'provider_payload_json' => $result['provider_payload_json'] ?? null,
            ];
        } catch (Throwable $exception) {
            return [
                'backend_key' => $backend->key(),
                'status' => 'failed',
                'result_count' => 0,
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'failure' => $this->failureClassifier()->classify($exception) + [
                    'exception_class' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            ];
        }
    }

    /**
     * @return array{
     *   matches:array<int, FaceSearchMatchData>,
     *   provider_payload_json?:array<string, mixed>,
     *   duration_ms:int
     * }
     */
    private function executeBackendSearch(
        FaceSearchBackendInterface $backend,
        Event $event,
        EventFaceSearchSetting $settings,
        EventMedia $probeMedia,
        string $binary,
        DetectedFaceData $face,
        int $topK,
    ): array {
        return $backend->searchBySelfie(
            event: $event,
            settings: $settings,
            probeMedia: $probeMedia,
            binary: $binary,
            face: $face,
            topK: $topK,
        );
    }

    /**
     * @param array<int, FaceSearchMatchData> $primaryMatches
     * @param array<int, FaceSearchMatchData> $shadowMatches
     * @return array<string, mixed>
     */
    private function compareShadowMatches(array $primaryMatches, array $shadowMatches): array
    {
        $primaryIds = array_values(array_unique(array_map(
            static fn (FaceSearchMatchData $match): int => $match->eventMediaId,
            $primaryMatches,
        )));
        $shadowIds = array_values(array_unique(array_map(
            static fn (FaceSearchMatchData $match): int => $match->eventMediaId,
            $shadowMatches,
        )));

        $sharedIds = array_values(array_intersect($primaryIds, $shadowIds));
        $primaryOnlyIds = array_values(array_diff($primaryIds, $shadowIds));
        $shadowOnlyIds = array_values(array_diff($shadowIds, $primaryIds));
        $unionCount = count(array_unique([...$primaryIds, ...$shadowIds]));

        return [
            'primary_result_count' => count($primaryIds),
            'shadow_result_count' => count($shadowIds),
            'shared_count' => count($sharedIds),
            'shared_event_media_ids' => $sharedIds,
            'primary_only_event_media_ids' => $primaryOnlyIds,
            'shadow_only_event_media_ids' => $shadowOnlyIds,
            'top_match_same' => ($primaryIds[0] ?? null) !== null && ($primaryIds[0] ?? null) === ($shadowIds[0] ?? null),
            'divergence_ratio' => $unionCount > 0
                ? round(1 - (count($sharedIds) / $unionCount), 6)
                : 0.0,
        ];
    }

    private function failureClassifier(): FaceSearchFailureClassifier
    {
        return $this->failureClassifier ?? app(FaceSearchFailureClassifier::class);
    }

    private function sourceLoader(): FaceSearchMediaSourceLoader
    {
        return $this->sourceLoader ?? app(FaceSearchMediaSourceLoader::class);
    }

    private function normalizeBackendKey(?string $backendKey): string
    {
        $resolvedKey = is_string($backendKey) && $backendKey !== ''
            ? $backendKey
            : 'local_pgvector';

        if ($resolvedKey === 'aws_rekognition' && ! isset($this->backends[$resolvedKey])) {
            return 'local_pgvector';
        }

        return $resolvedKey;
    }

    private function backendByKey(string $backendKey, bool $allowFallbackToLocal = true): FaceSearchBackendInterface
    {
        if (isset($this->backends[$backendKey])) {
            return $this->backends[$backendKey];
        }

        if ($allowFallbackToLocal && isset($this->backends['local_pgvector'])) {
            return $this->backends['local_pgvector'];
        }

        throw new InvalidArgumentException(sprintf('Nenhum backend de FaceSearch foi registrado para a chave [%s].', $backendKey));
    }

    private function ensureShadowBaselineSource(EventMedia $media): void
    {
        if (! $this->sourceLoader()->hasVariantSource($media, 'gallery')) {
            throw new RuntimeException('Shadow local baseline requires a gallery variant before indexing.');
        }
    }

    /**
     * @param array<string, mixed> $primaryResult
     * @return array<string, mixed>
     */
    private function alignLocalShadowBaselineWithPrimaryGate(
        EventMedia $media,
        FaceSearchBackendInterface $primary,
        FaceSearchBackendInterface $shadow,
        array $primaryResult,
    ): array {
        if ($primary->key() !== 'aws_rekognition' || $shadow->key() !== 'local_pgvector') {
            return [
                'status' => 'not_applicable',
            ];
        }

        $searchableCountBefore = EventMediaFace::query()
            ->where('event_media_id', $media->id)
            ->where('searchable', true)
            ->count();

        if ((int) ($primaryResult['faces_indexed'] ?? 0) > 0) {
            return [
                'status' => 'unchanged',
                'searchable_faces_before' => $searchableCountBefore,
                'searchable_faces_after' => $searchableCountBefore,
                'reason' => null,
            ];
        }

        $updated = EventMediaFace::query()
            ->where('event_media_id', $media->id)
            ->where('searchable', true)
            ->update([
                'searchable' => false,
            ]);

        return [
            'status' => $updated > 0 ? 'demoted_local_searchables' : 'unchanged',
            'searchable_faces_before' => $searchableCountBefore,
            'searchable_faces_after' => max(0, $searchableCountBefore - $updated),
            'reason' => $primaryResult['dominant_rejection_reason']
                ?? $primaryResult['skipped_reason']
                ?? 'primary_no_searchable_faces',
        ];
    }
}
