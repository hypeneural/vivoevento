<?php

namespace App\Modules\FaceSearch\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceQualityAssessmentData;
use App\Modules\FaceSearch\Models\EventFaceSearchRequest;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Queries\CollapseFaceSearchMatchesQuery;
use App\Modules\FaceSearch\Services\FaceDetectionProviderInterface;
use App\Modules\FaceSearch\Services\FaceEmbeddingProviderInterface;
use App\Modules\FaceSearch\Services\FaceQualityGateService;
use App\Modules\FaceSearch\Services\FaceVectorStoreInterface;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

class SearchFacesBySelfieAction
{
    public function __construct(
        private readonly FaceDetectionProviderInterface $detector,
        private readonly FaceEmbeddingProviderInterface $embedder,
        private readonly FaceVectorStoreInterface $vectorStore,
        private readonly FaceQualityGateService $qualityGate,
        private readonly CollapseFaceSearchMatchesQuery $collapseMatches,
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

        try {
            $binary = (string) file_get_contents($selfie->getRealPath());

            if ($binary === '') {
                throw ValidationException::withMessages([
                    'selfie' => ['Nao foi possivel ler a imagem enviada.'],
                ]);
            }

            $probeMedia = new EventMedia([
                'event_id' => $event->id,
                'media_type' => 'image',
                'source_type' => $publicSearch ? 'public_face_search' : 'internal_face_search',
            ]);

            $detectedFaces = array_values($this->detector->detect($probeMedia, $settings, $binary));

            $request->forceFill([
                'faces_detected' => count($detectedFaces),
            ])->save();

            ['face' => $face, 'assessment' => $assessment] = $this->resolveSearchFace($settings, $detectedFaces);

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

            $cropBinary = $this->cropFace($binary, $face);
            $embedding = $this->embedder->embed($probeMedia, $settings, $cropBinary, $face);

            $candidateLimit = min(200, max(1, (int) $settings->top_k) * 4);
            $collapsedMatches = $this->collapseMatches->execute(
                $this->vectorStore->search(
                    eventId: $event->id,
                    queryEmbedding: $embedding->vector,
                    topK: $candidateLimit,
                    threshold: $settings->search_threshold,
                    searchableOnly: true,
                    searchStrategy: $settings->search_strategy ?: (string) config('face_search.default_search_strategy', 'exact'),
                ),
            );

            $results = $this->resolveMediaResults(
                event: $event,
                collapsedMatches: $collapsedMatches,
                publicSearch: $publicSearch,
                includePending: $includePending,
                topK: max(1, (int) $settings->top_k),
            );

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

            return [
                'request' => $request->refresh(),
                'results' => $results,
            ];
        } catch (ValidationException $exception) {
            $request->forceFill([
                'status' => 'failed',
            ])->save();

            throw $exception;
        } catch (Throwable $exception) {
            $request->forceFill([
                'status' => 'failed',
            ])->save();

            throw $exception;
        }
    }

    /**
     * @param array<int, DetectedFaceData> $detectedFaces
     * @return array{face:DetectedFaceData, assessment:FaceQualityAssessmentData}
     */
    private function resolveSearchFace(
        EventFaceSearchSetting $settings,
        array $detectedFaces,
    ): array {
        if ($detectedFaces === []) {
            throw ValidationException::withMessages([
                'selfie' => ['Nao encontramos um rosto valido na selfie enviada.'],
            ]);
        }

        if (count($detectedFaces) > 1) {
            throw ValidationException::withMessages([
                'selfie' => ['Envie uma selfie com apenas uma pessoa visivel.'],
            ]);
        }

        $face = $detectedFaces[0];
        $assessment = $this->qualityGate->assess($face, $settings);

        return [
            'face' => $face,
            'assessment' => $assessment,
        ];
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

    private function cropFace(string $binary, DetectedFaceData $face): string
    {
        $cropped = Image::decode($binary)->crop(
            $face->boundingBox->width,
            $face->boundingBox->height,
            $face->boundingBox->x,
            $face->boundingBox->y,
        );

        return (string) $cropped->encodeUsingMediaType('image/webp', 88);
    }
}
