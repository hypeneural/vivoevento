<?php

namespace App\Modules\FaceSearch\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Enums\FaceSearchQueryStatus;
use App\Modules\FaceSearch\Models\EventFaceSearchRequest;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Models\FaceSearchQuery;
use App\Modules\FaceSearch\Queries\CollapseFaceSearchMatchesQuery;
use App\Modules\FaceSearch\Services\FaceSearchTelemetryService;
use App\Modules\FaceSearch\Services\FaceSearchRouter;
use App\Modules\FaceSearch\Services\SelfiePreflightService;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

class SearchFacesBySelfieAction
{
    public function __construct(
        private readonly FaceSearchRouter $router,
        private readonly SelfiePreflightService $selfiePreflight,
        private readonly CollapseFaceSearchMatchesQuery $collapseMatches,
        private readonly FaceSearchTelemetryService $telemetry,
    ) {}

    /**
     * @return array{
     *   request:EventFaceSearchRequest,
     *   results:array<int, array{
     *     rank:int,
     *     event_media_id:int,
     *     best_distance:float,
     *     best_quality_tier:string|null,
     *     best_quality_score:float|null,
     *     best_face_area_ratio:float|null,
     *     matched_face_ids:array<int, int>,
     *     media:EventMedia
     *   }>
     * }
     */
    public function execute(
        Event $event,
        UploadedFile $selfie,
        string $requesterType = 'user',
        ?User $requesterUser = null,
        ?string $consentVersion = null,
        string $selfieStorageStrategy = 'memory_only',
        bool $publicSearch = false,
        bool $includePending = true,
    ): array {
        $event->loadMissing('faceSearchSettings');

        $settings = $event->faceSearchSettings
            ?? new EventFaceSearchSetting(array_merge(
                ['event_id' => $event->id],
                EventFaceSearchSetting::defaultAttributes(),
            ));

        if (! $settings->enabled) {
            throw ValidationException::withMessages([
                'selfie' => ['A busca por selfie nao esta habilitada para este evento.'],
            ]);
        }

        if ($publicSearch && ! $event->allowsPublicSelfieSearch()) {
            throw ValidationException::withMessages([
                'selfie' => ['A busca publica por selfie nao esta disponivel para este evento.'],
            ]);
        }

        $request = EventFaceSearchRequest::query()->create([
            'event_id' => $event->id,
            'requester_type' => $requesterType,
            'requester_user_id' => $requesterUser?->id,
            'status' => 'processing',
            'consent_version' => $consentVersion,
            'selfie_storage_strategy' => $selfieStorageStrategy,
            'faces_detected' => 0,
            'top_k' => max(1, (int) $settings->top_k),
            'expires_at' => now()->addHours(max(1, (int) ($settings->selfie_retention_hours ?: 24))),
        ]);

        $query = null;

        try {
            $binary = (string) file_get_contents($selfie->getRealPath());

            if ($binary === '') {
                throw ValidationException::withMessages([
                    'selfie' => ['Nao foi possivel ler a imagem enviada.'],
                ]);
            }

            $preflight = $this->selfiePreflight->validateForSearch(
                event: $event,
                settings: $settings,
                binary: $binary,
                publicSearch: $publicSearch,
            );

            $request->forceFill([
                'faces_detected' => $preflight['detected_faces_count'],
            ])->save();

            /** @var EventMedia $probeMedia */
            $probeMedia = $preflight['probe_media'];
            $face = $preflight['face'];
            $assessment = $preflight['assessment'];

            $request->forceFill([
                'query_face_quality_score' => $face->qualityScore,
                'query_face_quality_tier' => $assessment->tier->value,
                'query_face_rejection_reason' => $assessment->reason,
            ])->save();

            if ($assessment->isRejected()) {
                throw ValidationException::withMessages([
                    'selfie' => ['A selfie precisa estar mais nitida e aproximada para a busca.'],
                ]);
            }

            $backend = $this->router->backendForSettings($settings);
            $query = FaceSearchQuery::query()->create([
                'event_id' => $event->id,
                'event_face_search_request_id' => $request->id,
                'backend_key' => $backend->key(),
                'fallback_backend_key' => $settings->fallback_backend_key,
                'routing_policy' => $settings->routing_policy,
                'status' => FaceSearchQueryStatus::Running,
                'query_media_path' => $this->queryMediaPath($selfie, $selfieStorageStrategy),
                'query_face_bbox_json' => [
                    'x' => $face->boundingBox->x,
                    'y' => $face->boundingBox->y,
                    'width' => $face->boundingBox->width,
                    'height' => $face->boundingBox->height,
                ],
                'result_count' => 0,
                'started_at' => now(),
            ]);

            $candidateLimit = min(200, max(1, (int) $settings->top_k) * 4);
            $execution = $this->router->executeSelfieSearch(
                event: $event,
                settings: $settings,
                probeMedia: $probeMedia,
                binary: $binary,
                face: $face,
                topK: $candidateLimit,
            );
            $collapsedMatches = $this->collapseMatches->execute(
                $execution['matches'] ?? [],
            );

            $results = $this->resolveMediaResults(
                event: $event,
                collapsedMatches: $collapsedMatches,
                publicSearch: $publicSearch,
                includePending: $includePending,
                topK: max(1, (int) $settings->top_k),
            );

            $query?->forceFill([
                'backend_key' => (string) ($execution['response_backend_key'] ?? $backend->key()),
                'status' => ($execution['query_status'] ?? FaceSearchQueryStatus::Completed->value) === FaceSearchQueryStatus::Degraded->value
                    ? FaceSearchQueryStatus::Degraded
                    : FaceSearchQueryStatus::Completed,
                'result_count' => count($results),
                'provider_payload_json' => $this->buildQueryAuditPayload($execution),
                'finished_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ])->save();

            $request->forceFill([
                'status' => 'completed',
                'query_face_quality_score' => $face->qualityScore,
                'query_face_quality_tier' => $assessment->tier->value,
                'query_face_rejection_reason' => $assessment->reason,
                'best_distance' => $results[0]['best_distance'] ?? null,
                'result_photo_ids_json' => array_values(array_map(
                    static fn (array $result) => $result['event_media_id'],
                    $results,
                )),
            ])->save();

            $request = $request->refresh();
            $query = $query?->refresh();

            $this->telemetry->recordQueryCompleted(
                event: $event,
                request: $request,
                query: $query,
                execution: $execution,
                results: $results,
                publicSearch: $publicSearch,
            );

            return [
                'request' => $request,
                'results' => $results,
            ];
        } catch (ValidationException $exception) {
            $query?->forceFill([
                'status' => FaceSearchQueryStatus::Failed,
                'result_count' => 0,
                'error_code' => 'validation_failed',
                'error_message' => collect($exception->errors())->flatten()->first(),
                'finished_at' => now(),
            ])->save();

            $request->forceFill([
                'status' => 'failed',
            ])->save();

            $this->telemetry->recordQueryValidationFailed(
                event: $event,
                request: $request->refresh(),
                query: $query?->refresh(),
                exception: $exception,
                publicSearch: $publicSearch,
            );

            throw $exception;
        } catch (Throwable $exception) {
            $query?->forceFill([
                'status' => FaceSearchQueryStatus::Failed,
                'result_count' => 0,
                'error_code' => class_basename($exception),
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();

            $request->forceFill([
                'status' => 'failed',
            ])->save();

            $this->telemetry->recordQueryFailed(
                event: $event,
                request: $request->refresh(),
                query: $query?->refresh(),
                exception: $exception,
                publicSearch: $publicSearch,
            );

            throw $exception;
        }
    }

    private function queryMediaPath(UploadedFile $selfie, string $storageStrategy): string
    {
        $name = $selfie->getClientOriginalName() ?: $selfie->getFilename() ?: 'selfie-upload';

        return sprintf('%s://%s', $storageStrategy !== '' ? $storageStrategy : 'memory_only', $name);
    }

    /**
     * @param array<string, mixed> $execution
     * @return array<string, mixed>
     */
    private function buildQueryAuditPayload(array $execution): array
    {
        return array_filter([
            'primary_backend_key' => $execution['primary_backend_key'] ?? null,
            'response_backend_key' => $execution['response_backend_key'] ?? null,
            'fallback_backend_key' => $execution['fallback_backend_key'] ?? null,
            'fallback_triggered' => $execution['fallback_triggered'] ?? false,
            'primary_duration_ms' => $execution['primary_duration_ms'] ?? null,
            'response_duration_ms' => $execution['response_duration_ms'] ?? null,
            'primary_failure' => $execution['primary_failure'] ?? null,
            'shadow' => $execution['shadow'] ?? null,
            'provider_response' => $execution['provider_payload_json'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param array<int, array{
     *   event_media_id:int,
     *   best_distance:float,
     *   best_quality_tier:string|null,
     *   best_quality_score:float|null,
     *   best_face_area_ratio:float|null,
     *   matched_face_ids:array<int, int>
     * }> $collapsedMatches
     * @return array<int, array{
     *   rank:int,
     *   event_media_id:int,
     *   best_distance:float,
     *   best_quality_tier:string|null,
     *   best_quality_score:float|null,
     *   best_face_area_ratio:float|null,
     *   matched_face_ids:array<int, int>,
     *   media:EventMedia
     * }>
     */
    private function resolveMediaResults(
        Event $event,
        array $collapsedMatches,
        bool $publicSearch,
        bool $includePending,
        int $topK,
    ): array {
        if ($collapsedMatches === []) {
            return [];
        }

        $mediaById = $this->loadAllowedMedia(
            event: $event,
            mediaIds: array_values(array_map(
                static fn (array $match) => $match['event_media_id'],
                $collapsedMatches,
            )),
            publicSearch: $publicSearch,
            includePending: $includePending,
        );

        $results = collect($collapsedMatches)
            ->filter(fn (array $match) => $mediaById->has($match['event_media_id']))
            ->map(fn (array $match) => [
                ...$match,
                'media' => $mediaById->get($match['event_media_id']),
            ])
            ->sort(function (array $left, array $right): int {
                $distance = $left['best_distance'] <=> $right['best_distance'];

                if ($distance !== 0) {
                    return $distance;
                }

                $leftPublished = $left['media']->publication_status?->value === PublicationStatus::Published->value ? 0 : 1;
                $rightPublished = $right['media']->publication_status?->value === PublicationStatus::Published->value ? 0 : 1;
                $published = $leftPublished <=> $rightPublished;

                if ($published !== 0) {
                    return $published;
                }

                $tier = \App\Modules\FaceSearch\Enums\FaceQualityTier::rankFor($right['best_quality_tier'] ?? null)
                    <=> \App\Modules\FaceSearch\Enums\FaceQualityTier::rankFor($left['best_quality_tier'] ?? null);

                if ($tier !== 0) {
                    return $tier;
                }

                $quality = ($right['best_quality_score'] ?? -1.0) <=> ($left['best_quality_score'] ?? -1.0);

                if ($quality !== 0) {
                    return $quality;
                }

                return ($right['best_face_area_ratio'] ?? -1.0) <=> ($left['best_face_area_ratio'] ?? -1.0);
            })
            ->take($topK)
            ->values()
            ->map(fn (array $match, int $index) => [
                'rank' => $index + 1,
                ...$match,
            ])
            ->all();

        return $results;
    }

    /**
     * @param array<int, int> $mediaIds
     * @return Collection<int, EventMedia>
     */
    private function loadAllowedMedia(
        Event $event,
        array $mediaIds,
        bool $publicSearch,
        bool $includePending,
    ): Collection {
        $query = EventMedia::query()
            ->with(['event.faceSearchSettings', 'variants', 'inboundMessage'])
            ->where('event_id', $event->id)
            ->whereIn('id', $mediaIds);

        if ($publicSearch) {
            $query->approved()->published();
        } else {
            $query
                ->where('publication_status', '!=', PublicationStatus::Deleted->value)
                ->where('moderation_status', '!=', ModerationStatus::Rejected->value);

            if (! $includePending) {
                $query->where('moderation_status', ModerationStatus::Approved->value);
            }
        }

        /** @var EloquentCollection<int, EventMedia> $media */
        $media = $query->get();

        return $media->keyBy('id');
    }
}
