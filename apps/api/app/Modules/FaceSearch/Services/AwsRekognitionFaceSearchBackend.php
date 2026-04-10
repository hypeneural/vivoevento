<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceBoundingBoxData;
use App\Modules\FaceSearch\DTOs\FaceSearchMatchData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Models\FaceSearchProviderRecord;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Services\ProviderCircuitBreaker;
use App\Modules\MediaProcessing\Models\EventMedia;
use Aws\Exception\AwsException;
use Aws\Rekognition\RekognitionClient;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

class AwsRekognitionFaceSearchBackend implements FaceSearchBackendInterface
{
    public function __construct(
        private readonly AwsRekognitionClientFactory $clients,
        private readonly AwsImagePreprocessor $preprocessor = new AwsImagePreprocessor,
        private readonly FaceQualityGateService $qualityGate = new FaceQualityGateService,
        private readonly FaceSearchMediaSourceLoader $sourceLoader = new FaceSearchMediaSourceLoader,
        private readonly ?ProviderCircuitBreaker $circuitBreaker = null,
        private readonly ?FaceSearchTelemetryService $telemetry = null,
    ) {}

    public function key(): string
    {
        return 'aws_rekognition';
    }

    public function ensureEventBackend(Event $event, EventFaceSearchSetting $settings): array
    {
        $collectionId = $this->resolveCollectionId($event, $settings);
        $client = $this->rekognitionClient($settings, 'index');

        try {
            $this->callAws(
                operation: 'index',
                callback: fn () => $client->createCollection([
                    'CollectionId' => $collectionId,
                ]),
                context: [
                    'event_id' => $event->id,
                    'collection_id' => $collectionId,
                    'aws_operation' => 'CreateCollection',
                ],
                suppressAwsErrorCodes: ['ResourceAlreadyExistsException'],
            );
        } catch (AwsException $exception) {
            if (! $this->isResourceAlreadyExists($exception)) {
                throw $exception;
            }
        }

        $description = $this->toArray($this->callAws(
            operation: 'query',
            callback: fn () => $client->describeCollection([
                'CollectionId' => $collectionId,
            ]),
            context: [
                'event_id' => $event->id,
                'collection_id' => $collectionId,
                'aws_operation' => 'DescribeCollection',
            ],
        ));

        $settings->forceFill([
            'aws_collection_id' => $collectionId,
            'aws_collection_arn' => (string) ($description['CollectionARN'] ?? $settings->aws_collection_arn),
            'aws_face_model_version' => (string) ($description['FaceModelVersion'] ?? $settings->aws_face_model_version),
        ])->save();

        return [
            'backend_key' => $this->key(),
            'status' => 'ready',
            'collection_id' => $collectionId,
            'collection_arn' => $settings->aws_collection_arn,
            'face_model_version' => $settings->aws_face_model_version,
            'face_count' => (int) ($description['FaceCount'] ?? 0),
        ];
    }

    public function indexMedia(EventMedia $media, EventFaceSearchSetting $settings): array
    {
        $media->loadMissing(['event', 'variants']);

        $source = $this->sourceLoader->loadImageBinary($media);
        $prepared = $this->preprocessor->prepare($source['binary'], [
            'max_dimension' => 1920,
            'max_bytes' => 5_242_880,
        ]);

        $collectionId = $this->resolveCollectionId($media->event, $settings);
        $client = $this->rekognitionClient($settings, 'index');
        $externalImageId = $this->externalImageId($media, $prepared['binary']);

        $this->cleanupExistingProviderRecords($client, $media, $collectionId);

        try {
            $response = $this->toArray($this->callAws(
                operation: 'index',
                callback: fn () => $client->indexFaces([
                    'CollectionId' => $collectionId,
                    'Image' => [
                        'Bytes' => $prepared['binary'],
                    ],
                    'ExternalImageId' => $externalImageId,
                    'QualityFilter' => $settings->aws_index_quality_filter,
                    'MaxFaces' => $this->resolveMaxFaces($settings),
                    'DetectionAttributes' => $this->resolveDetectionAttributes($settings),
                ]),
                context: [
                    'event_id' => $media->event_id,
                    'event_media_id' => $media->id,
                    'collection_id' => $collectionId,
                    'aws_operation' => 'IndexFaces',
                    'external_image_id' => $externalImageId,
                ],
            ));
        } catch (AwsException $exception) {
            if ($this->isNoFaceFound($exception)) {
                return [
                    'status' => 'skipped',
                    'source_ref' => $source['source_ref'],
                    'faces_detected' => 0,
                    'faces_indexed' => 0,
                    'skipped_reason' => 'no_faces_detected',
                ];
            }

            throw $exception;
        }

        $searchableFaceIds = [];
        $facesDetected = 0;
        $facesIndexed = 0;
        $dominantRejectionReason = null;
        $qualitySummary = [
            'reject' => 0,
            'index_only' => 0,
            'search_priority' => 0,
        ];

        foreach ((array) ($response['FaceRecords'] ?? []) as $recordPayload) {
            $facesDetected++;

            $recordPayload = (array) $recordPayload;
            $facePayload = (array) ($recordPayload['Face'] ?? []);
            $faceDetail = (array) ($recordPayload['FaceDetail'] ?? []);
            $detectedFace = $this->mapDetectedFace($facePayload, $faceDetail, $prepared['width'], $prepared['height']);
            $assessment = $this->qualityGate->assess($detectedFace, $settings);
            $qualitySummary[$assessment->tier->value]++;

            $searchable = ! $assessment->isRejected() && $media->moderation_status !== ModerationStatus::Rejected;

            if (! $searchable) {
                $dominantRejectionReason ??= $assessment->reason ?? 'media_not_searchable';
            }

            FaceSearchProviderRecord::query()->create([
                'event_id' => $media->event_id,
                'event_media_id' => $media->id,
                'provider_key' => $this->key(),
                'backend_key' => $this->key(),
                'collection_id' => $collectionId,
                'face_id' => $facePayload['FaceId'] ?? null,
                'image_id' => $facePayload['ImageId'] ?? null,
                'external_image_id' => $facePayload['ExternalImageId'] ?? $externalImageId,
                'bbox_json' => $this->boundingBoxArray($detectedFace),
                'landmarks_json' => $detectedFace->landmarks,
                'pose_json' => [
                    'yaw' => $detectedFace->poseYaw,
                    'pitch' => $detectedFace->posePitch,
                    'roll' => $detectedFace->poseRoll,
                ],
                'quality_json' => [
                    'composed_quality_score' => $detectedFace->qualityScore,
                    'sharpness' => data_get($faceDetail, 'Quality.Sharpness'),
                    'brightness' => data_get($faceDetail, 'Quality.Brightness'),
                    'confidence' => $facePayload['Confidence'] ?? null,
                    'face_area_ratio' => $detectedFace->faceAreaRatio,
                    'quality_tier' => $assessment->tier->value,
                    'quality_rejection_reason' => $assessment->reason,
                ],
                'unindexed_reasons_json' => $searchable
                    ? []
                    : array_values(array_filter([
                        $assessment->reason,
                        $media->moderation_status === ModerationStatus::Rejected ? 'media_rejected' : null,
                    ])),
                'searchable' => $searchable,
                'indexed_at' => now(),
                'provider_payload_json' => $recordPayload,
            ]);

            if ($searchable && isset($facePayload['FaceId']) && is_string($facePayload['FaceId'])) {
                $searchableFaceIds[] = $facePayload['FaceId'];
                $facesIndexed++;
            }
        }

        foreach ((array) ($response['UnindexedFaces'] ?? []) as $unindexedPayload) {
            $facesDetected++;

            $unindexedPayload = (array) $unindexedPayload;
            $faceDetail = (array) ($unindexedPayload['FaceDetail'] ?? []);
            $detectedFace = $this->mapDetectedFace([], $faceDetail, $prepared['width'], $prepared['height']);

            FaceSearchProviderRecord::query()->create([
                'event_id' => $media->event_id,
                'event_media_id' => $media->id,
                'provider_key' => $this->key(),
                'backend_key' => $this->key(),
                'collection_id' => $collectionId,
                'face_id' => null,
                'image_id' => null,
                'external_image_id' => $externalImageId,
                'bbox_json' => $this->boundingBoxArray($detectedFace),
                'landmarks_json' => $detectedFace->landmarks,
                'pose_json' => [
                    'yaw' => $detectedFace->poseYaw,
                    'pitch' => $detectedFace->posePitch,
                    'roll' => $detectedFace->poseRoll,
                ],
                'quality_json' => [
                    'composed_quality_score' => $detectedFace->qualityScore,
                    'sharpness' => data_get($faceDetail, 'Quality.Sharpness'),
                    'brightness' => data_get($faceDetail, 'Quality.Brightness'),
                    'face_area_ratio' => $detectedFace->faceAreaRatio,
                ],
                'unindexed_reasons_json' => array_values((array) ($unindexedPayload['Reasons'] ?? [])),
                'searchable' => false,
                'indexed_at' => now(),
                'provider_payload_json' => $unindexedPayload,
            ]);
        }

        $rejectedFaceIds = FaceSearchProviderRecord::query()
            ->where('event_media_id', $media->id)
            ->where('backend_key', $this->key())
            ->where('searchable', false)
            ->whereNotNull('face_id')
            ->pluck('face_id')
            ->filter(fn (mixed $faceId): bool => is_string($faceId) && $faceId !== '')
            ->values()
            ->all();

        if ($rejectedFaceIds !== []) {
            $this->callAws(
                operation: 'index',
                callback: fn () => $client->deleteFaces([
                    'CollectionId' => $collectionId,
                    'FaceIds' => $rejectedFaceIds,
                ]),
                context: [
                    'event_id' => $media->event_id,
                    'event_media_id' => $media->id,
                    'collection_id' => $collectionId,
                    'aws_operation' => 'DeleteFaces',
                    'face_count' => count($rejectedFaceIds),
                ],
            );
        }

        return [
            'status' => $facesIndexed > 0 ? 'indexed' : 'skipped',
            'source_ref' => $source['source_ref'],
            'faces_detected' => $facesDetected,
            'faces_indexed' => $facesIndexed,
            'quality_summary' => $qualitySummary,
            'dominant_rejection_reason' => $dominantRejectionReason,
            'skipped_reason' => $facesIndexed > 0 ? null : 'no_faces_after_quality_gate',
        ];
    }

    public function searchBySelfie(
        Event $event,
        EventFaceSearchSetting $settings,
        EventMedia $probeMedia,
        string $binary,
        DetectedFaceData $face,
        int $topK,
    ): array {
        $prepared = $this->preprocessor->prepare($binary, [
            'max_dimension' => 1920,
            'max_bytes' => 5_242_880,
        ]);
        $collectionId = $this->resolveCollectionId($event, $settings);
        $requestedMode = $settings->aws_search_mode === 'users' ? 'users' : 'faces';
        $canUseUsers = $requestedMode === 'users' && $this->hasReadyUserVectors($event, $collectionId);

        try {
            if ($canUseUsers) {
                $response = $this->searchUsersByImage($collectionId, $prepared['binary'], $settings, $topK);
                $matches = $this->resolveUserMatches($event, $collectionId, $response);

                if ($matches !== []) {
                    return [
                        'matches' => $matches,
                        'provider_payload_json' => [
                            ...$response,
                            'search_mode_requested' => $requestedMode,
                            'search_mode_resolved' => 'users',
                        ],
                    ];
                }

                return [
                    'matches' => [],
                    'provider_payload_json' => [
                        ...$response,
                        'search_mode_requested' => $requestedMode,
                        'search_mode_resolved' => 'users',
                    ],
                ];
            }

            $response = $this->searchFacesByImage($collectionId, $prepared['binary'], $settings, $topK);
        } catch (AwsException $exception) {
            if ($this->isNoFaceFound($exception)) {
                throw ValidationException::withMessages([
                    'selfie' => ['A selfie precisa mostrar uma face nitida para a busca.'],
                ]);
            }

            throw $exception;
        }

        $matches = $this->resolveFaceMatches($event, $collectionId, $response);

        return [
            'matches' => $matches,
            'provider_payload_json' => [
                ...$response,
                'search_mode_requested' => $requestedMode,
                'search_mode_resolved' => 'faces',
                'search_mode_fallback_reason' => $requestedMode === 'users' && ! $canUseUsers
                    ? 'user_vector_not_ready'
                    : null,
            ],
        ];
    }

    /**
     * @param array<int, string> $faceIds
     * @return array<string, mixed>
     */
    public function syncUserVector(
        Event $event,
        EventFaceSearchSetting $settings,
        string $userId,
        array $faceIds,
    ): array {
        $collectionId = $this->resolveCollectionId($event, $settings);
        $requestedFaceIds = collect($faceIds)
            ->filter(fn (mixed $faceId): bool => is_string($faceId) && trim($faceId) !== '')
            ->map(fn (string $faceId): string => trim($faceId))
            ->unique()
            ->values()
            ->all();

        if ($requestedFaceIds === []) {
            return [
                'user_id' => $userId,
                'requested_face_count' => 0,
                'associated_face_count' => 0,
                'unsuccessful_face_count' => 0,
                'user_status' => 'CREATED',
            ];
        }

        $client = $this->rekognitionClient($settings, 'index');

        try {
            $this->callAws(
                operation: 'index',
                callback: fn () => $client->createUser([
                    'CollectionId' => $collectionId,
                    'UserId' => $userId,
                    'ClientRequestToken' => $this->idempotencyToken('create-user', $collectionId, $userId),
                ]),
                context: [
                    'event_id' => $event->id,
                    'collection_id' => $collectionId,
                    'aws_operation' => 'CreateUser',
                    'user_id' => $userId,
                ],
                suppressAwsErrorCodes: ['ConflictException'],
            );
        } catch (AwsException $exception) {
            if (($exception->getAwsErrorCode() ?? null) !== 'ConflictException') {
                throw $exception;
            }
        }

        $associatedFaceIds = [];
        $unsuccessfulByFaceId = [];
        $userStatus = 'CREATED';

        foreach (array_chunk($requestedFaceIds, 100) as $chunkIndex => $chunkFaceIds) {
            $response = $this->toArray($this->callAws(
                operation: 'index',
                callback: fn () => $client->associateFaces([
                    'CollectionId' => $collectionId,
                    'UserId' => $userId,
                    'FaceIds' => $chunkFaceIds,
                    'UserMatchThreshold' => (float) $settings->aws_associate_user_match_threshold,
                    'ClientRequestToken' => $this->idempotencyToken(
                        'associate-faces',
                        $collectionId,
                        $userId,
                        (string) $chunkIndex,
                        implode(',', $chunkFaceIds),
                    ),
                ]),
                context: [
                    'event_id' => $event->id,
                    'collection_id' => $collectionId,
                    'aws_operation' => 'AssociateFaces',
                    'user_id' => $userId,
                    'requested_face_count' => count($chunkFaceIds),
                ],
            ));

            $userStatus = is_string($response['UserStatus'] ?? null) ? $response['UserStatus'] : $userStatus;

            foreach ((array) ($response['AssociatedFaces'] ?? []) as $associatedFace) {
                $associatedFaceId = data_get($associatedFace, 'FaceId');

                if (is_string($associatedFaceId) && $associatedFaceId !== '') {
                    $associatedFaceIds[] = $associatedFaceId;
                }
            }

            foreach ((array) ($response['UnsuccessfulFaceAssociations'] ?? []) as $unsuccessful) {
                $faceId = data_get($unsuccessful, 'FaceId');

                if (is_string($faceId) && $faceId !== '') {
                    $unsuccessfulByFaceId[$faceId] = (array) $unsuccessful;
                }
            }
        }

        $records = FaceSearchProviderRecord::query()
            ->where('event_id', $event->id)
            ->where('backend_key', $this->key())
            ->where('collection_id', $collectionId)
            ->whereIn('face_id', $requestedFaceIds)
            ->get();

        $syncedAt = now()->toIso8601String();

        foreach ($records as $record) {
            $failure = $unsuccessfulByFaceId[$record->face_id] ?? null;
            $payload = is_array($record->provider_payload_json) ? $record->provider_payload_json : [];
            $awsUserVector = [
                'status' => $failure === null ? 'synced' : 'failed',
                'user_id' => $userId,
                'synced_at' => $syncedAt,
                'user_status' => $userStatus,
                'requested_face_count' => count($requestedFaceIds),
                'associated_face_count' => count(array_unique($associatedFaceIds)),
                'unsuccessful_face_count' => count($unsuccessfulByFaceId),
                'association' => [
                    'result' => $failure === null ? 'associated' : 'failed',
                    'confidence' => $failure['Confidence'] ?? null,
                    'reasons' => array_values((array) ($failure['Reasons'] ?? [])),
                    'reported_user_id' => $failure['UserId'] ?? $userId,
                ],
            ];

            $record->forceFill([
                'user_id' => $failure === null ? $userId : $record->user_id,
                'provider_payload_json' => [
                    ...$payload,
                    'aws_user_vector' => $awsUserVector,
                ],
            ])->save();
        }

        return [
            'user_id' => $userId,
            'requested_face_count' => count($requestedFaceIds),
            'associated_face_count' => count(array_unique($associatedFaceIds)),
            'unsuccessful_face_count' => count($unsuccessfulByFaceId),
            'user_status' => $userStatus,
        ];
    }

    private function searchFacesByImage(
        string $collectionId,
        string $binary,
        EventFaceSearchSetting $settings,
        int $topK,
    ): array {
        return $this->toArray($this->callAws(
            operation: 'query',
            callback: function () use ($collectionId, $binary, $settings, $topK) {
                $client = $this->rekognitionClient($settings, 'query');

                return $client->searchFacesByImage([
                    'CollectionId' => $collectionId,
                    'Image' => [
                        'Bytes' => $binary,
                    ],
                    'FaceMatchThreshold' => (float) $settings->aws_search_face_match_threshold,
                    'MaxFaces' => max(1, min(4096, $topK)),
                    'QualityFilter' => $settings->aws_search_faces_quality_filter,
                ]);
            },
            context: [
                'collection_id' => $collectionId,
                'aws_operation' => 'SearchFacesByImage',
                'top_k' => $topK,
                'match_threshold' => (float) $settings->aws_search_face_match_threshold,
            ],
        ));
    }

    private function searchUsersByImage(
        string $collectionId,
        string $binary,
        EventFaceSearchSetting $settings,
        int $topK,
    ): array {
        return $this->toArray($this->callAws(
            operation: 'query',
            callback: function () use ($collectionId, $binary, $settings, $topK) {
                $client = $this->rekognitionClient($settings, 'query');

                return $client->searchUsersByImage([
                    'CollectionId' => $collectionId,
                    'Image' => [
                        'Bytes' => $binary,
                    ],
                    'UserMatchThreshold' => (float) $settings->aws_search_user_match_threshold,
                    'MaxUsers' => max(1, min(500, $topK)),
                    'QualityFilter' => $settings->aws_search_users_quality_filter,
                ]);
            },
            context: [
                'collection_id' => $collectionId,
                'aws_operation' => 'SearchUsersByImage',
                'top_k' => $topK,
                'match_threshold' => (float) $settings->aws_search_user_match_threshold,
            ],
        ));
    }

    /**
     * @param array<string, mixed> $response
     * @return array<int, FaceSearchMatchData>
     */
    private function resolveFaceMatches(Event $event, string $collectionId, array $response): array
    {
        $faceIds = collect((array) ($response['FaceMatches'] ?? []))
            ->map(fn (mixed $match): ?string => data_get($match, 'Face.FaceId'))
            ->filter(fn (?string $faceId): bool => is_string($faceId) && $faceId !== '')
            ->values()
            ->all();

        $recordsByFaceId = FaceSearchProviderRecord::query()
            ->where('event_id', $event->id)
            ->where('backend_key', $this->key())
            ->where('collection_id', $collectionId)
            ->where('searchable', true)
            ->whereIn('face_id', $faceIds)
            ->get()
            ->keyBy('face_id');

        $matches = collect((array) ($response['FaceMatches'] ?? []))
            ->map(function (mixed $match) use ($recordsByFaceId): ?FaceSearchMatchData {
                $faceId = data_get($match, 'Face.FaceId');

                if (! is_string($faceId) || $faceId === '' || ! $recordsByFaceId->has($faceId)) {
                    return null;
                }

                /** @var FaceSearchProviderRecord $record */
                $record = $recordsByFaceId->get($faceId);
                $quality = (array) ($record->quality_json ?? []);

                return new FaceSearchMatchData(
                    faceId: (int) $record->id,
                    eventMediaId: (int) $record->event_media_id,
                    distance: round(1 - (((float) data_get($match, 'Similarity', 0.0)) / 100), 6),
                    qualityScore: isset($quality['composed_quality_score']) ? (float) $quality['composed_quality_score'] : null,
                    faceAreaRatio: isset($quality['face_area_ratio']) ? (float) $quality['face_area_ratio'] : null,
                    qualityTier: is_string($quality['quality_tier'] ?? null) ? $quality['quality_tier'] : null,
                );
            })
            ->filter()
            ->values()
            ->all();

        return $matches;
    }

    /**
     * @param array<string, mixed> $response
     * @return array<int, FaceSearchMatchData>
     */
    private function resolveUserMatches(Event $event, string $collectionId, array $response): array
    {
        $userIds = collect((array) ($response['UserMatches'] ?? []))
            ->map(fn (mixed $match): ?string => data_get($match, 'User.UserId'))
            ->filter(fn (?string $userId): bool => is_string($userId) && $userId !== '')
            ->values()
            ->all();

        $recordsByUserId = FaceSearchProviderRecord::query()
            ->where('event_id', $event->id)
            ->where('backend_key', $this->key())
            ->where('collection_id', $collectionId)
            ->where('searchable', true)
            ->whereIn('user_id', $userIds)
            ->get()
            ->groupBy('user_id');

        return collect((array) ($response['UserMatches'] ?? []))
            ->map(function (mixed $match) use ($recordsByUserId): ?FaceSearchMatchData {
                $userId = data_get($match, 'User.UserId');

                if (! is_string($userId) || $userId === '' || ! $recordsByUserId->has($userId)) {
                    return null;
                }

                /** @var FaceSearchProviderRecord|null $record */
                $record = $recordsByUserId->get($userId)
                    ?->sort(function (FaceSearchProviderRecord $left, FaceSearchProviderRecord $right): int {
                        $leftQuality = is_array($left->quality_json) ? $left->quality_json : [];
                        $rightQuality = is_array($right->quality_json) ? $right->quality_json : [];
                        $tier = \App\Modules\FaceSearch\Enums\FaceQualityTier::rankFor($rightQuality['quality_tier'] ?? null)
                            <=> \App\Modules\FaceSearch\Enums\FaceQualityTier::rankFor($leftQuality['quality_tier'] ?? null);

                        if ($tier !== 0) {
                            return $tier;
                        }

                        $quality = ((float) ($rightQuality['composed_quality_score'] ?? 0.0))
                            <=> ((float) ($leftQuality['composed_quality_score'] ?? 0.0));

                        if ($quality !== 0) {
                            return $quality;
                        }

                        return ((int) $left->id) <=> ((int) $right->id);
                    })
                    ->first();

                if (! $record instanceof FaceSearchProviderRecord) {
                    return null;
                }

                $quality = is_array($record->quality_json) ? $record->quality_json : [];

                return new FaceSearchMatchData(
                    faceId: (int) $record->id,
                    eventMediaId: (int) $record->event_media_id,
                    distance: round(1 - (((float) data_get($match, 'Similarity', 0.0)) / 100), 6),
                    qualityScore: isset($quality['composed_quality_score']) ? (float) $quality['composed_quality_score'] : null,
                    faceAreaRatio: isset($quality['face_area_ratio']) ? (float) $quality['face_area_ratio'] : null,
                    qualityTier: is_string($quality['quality_tier'] ?? null) ? $quality['quality_tier'] : null,
                );
            })
            ->filter()
            ->values()
            ->all();
    }

    private function hasReadyUserVectors(Event $event, string $collectionId): bool
    {
        return FaceSearchProviderRecord::query()
            ->where('event_id', $event->id)
            ->where('backend_key', $this->key())
            ->where('collection_id', $collectionId)
            ->where('searchable', true)
            ->whereNotNull('user_id')
            ->exists();
    }

    private function idempotencyToken(string $prefix, string ...$parts): string
    {
        $hash = hash('sha256', implode('|', $parts));

        return substr($prefix . '-' . $hash, 0, 64);
    }

    public function healthCheck(Event $event, EventFaceSearchSetting $settings): array
    {
        $region = $this->resolveRegion($settings);
        $requiredActions = $this->requiredActions();
        $result = [
            'backend_key' => $this->key(),
            'status' => 'healthy',
            'required_actions' => $requiredActions,
            'checks' => [
                'identity' => 'pending',
                'collection' => 'pending',
                'list_faces' => 'pending',
            ],
            'identity' => null,
            'collection' => null,
            'error_code' => null,
            'error_message' => null,
        ];

        if (! is_string($settings->aws_collection_id) || trim($settings->aws_collection_id) === '') {
            return [
                ...$result,
                'status' => 'misconfigured',
                'checks' => [
                    'identity' => 'skipped',
                    'collection' => 'failed',
                    'list_faces' => 'skipped',
                ],
                'error_code' => 'missing_collection',
                'error_message' => 'AWS collection is not provisioned for this event.',
            ];
        }

        try {
            $sts = $this->clients->makeStsClient([
                'region' => $region,
            ]);
            $identity = $this->toArray($this->callAws(
                operation: 'query',
                callback: fn () => $sts->getCallerIdentity(),
            ));

            $result['identity'] = [
                'account' => $identity['Account'] ?? null,
                'arn' => $identity['Arn'] ?? null,
                'user_id' => $identity['UserId'] ?? null,
            ];
            $result['checks']['identity'] = 'ok';
        } catch (Throwable $exception) {
            return $this->healthFailure($result, 'provider_unavailable', 'identity', $exception);
        }

        try {
            $client = $this->rekognitionClient($settings, 'query');
            $description = $this->toArray($this->callAws(
                operation: 'query',
                callback: fn () => $client->describeCollection([
                    'CollectionId' => $settings->aws_collection_id,
                ]),
            ));
            $result['collection'] = [
                'collection_id' => $settings->aws_collection_id,
                'collection_arn' => $description['CollectionARN'] ?? $settings->aws_collection_arn,
                'face_model_version' => $description['FaceModelVersion'] ?? $settings->aws_face_model_version,
                'face_count' => (int) ($description['FaceCount'] ?? 0),
            ];
            $result['checks']['collection'] = 'ok';

            $this->callAws(
                operation: 'query',
                callback: fn () => $client->listFaces([
                    'CollectionId' => $settings->aws_collection_id,
                    'MaxResults' => 1,
                ]),
            );
            $result['checks']['list_faces'] = 'ok';

            return $result;
        } catch (Throwable $exception) {
            return $this->healthFailure($result, $this->healthStatusFor($exception), 'collection', $exception);
        }
    }

    /**
     * @return array{
     *   backend_key:string,
     *   collection_id:string,
     *   remote_face_count:int,
     *   local_face_count_before:int,
     *   matched_records:int,
     *   restored_records:int,
     *   remote_only_records_created:int,
     *   local_only_records_soft_deleted:int
     * }
     */
    public function reconcileCollection(Event $event, EventFaceSearchSetting $settings): array
    {
        $collectionId = $this->resolveCollectionId($event, $settings);
        $client = $this->rekognitionClient($settings, 'query');

        $description = $this->toArray($this->callAws(
            operation: 'query',
            callback: fn () => $client->describeCollection([
                'CollectionId' => $collectionId,
            ]),
        ));

        $settings->forceFill([
            'aws_collection_id' => $collectionId,
            'aws_collection_arn' => (string) ($description['CollectionARN'] ?? $settings->aws_collection_arn),
            'aws_face_model_version' => (string) ($description['FaceModelVersion'] ?? $settings->aws_face_model_version),
        ])->save();

        $remoteFaces = collect($this->listAllFaces($client, $collectionId))
            ->filter(fn (mixed $face): bool => is_array($face) && is_string($face['FaceId'] ?? null))
            ->keyBy(fn (array $face): string => (string) $face['FaceId']);

        $localRecords = FaceSearchProviderRecord::query()
            ->withTrashed()
            ->where('event_id', $event->id)
            ->where('backend_key', $this->key())
            ->where('collection_id', $collectionId)
            ->whereNotNull('face_id')
            ->get()
            ->keyBy('face_id');

        $localActiveRecords = $localRecords->filter(fn (FaceSearchProviderRecord $record): bool => ! $record->trashed());
        $matchedRecords = 0;
        $restoredRecords = 0;
        $remoteOnlyRecordsCreated = 0;

        foreach ($remoteFaces as $faceId => $remoteFace) {
            /** @var array<string, mixed> $remoteFace */
            $remoteFace = (array) $remoteFace;

            if ($localRecords->has($faceId)) {
                /** @var FaceSearchProviderRecord $record */
                $record = $localRecords->get($faceId);

                if ($record->trashed()) {
                    $record->restore();
                    $restoredRecords++;
                } else {
                    $matchedRecords++;
                }

                $record->forceFill([
                    'image_id' => $remoteFace['ImageId'] ?? $record->image_id,
                    'external_image_id' => $remoteFace['ExternalImageId'] ?? $record->external_image_id,
                    'bbox_json' => $this->listFaceBoundingBoxArray($remoteFace),
                    'provider_payload_json' => $remoteFace,
                    'indexed_at' => now(),
                ])->save();

                continue;
            }

            FaceSearchProviderRecord::query()->create([
                'event_id' => $event->id,
                'event_media_id' => $this->resolveMediaIdFromExternalImageId($event, $remoteFace['ExternalImageId'] ?? null),
                'provider_key' => $this->key(),
                'backend_key' => $this->key(),
                'collection_id' => $collectionId,
                'face_id' => $faceId,
                'image_id' => $remoteFace['ImageId'] ?? null,
                'external_image_id' => $remoteFace['ExternalImageId'] ?? null,
                'bbox_json' => $this->listFaceBoundingBoxArray($remoteFace),
                'quality_json' => [
                    'confidence' => $remoteFace['Confidence'] ?? null,
                    'reconciliation_source' => 'list_faces',
                ],
                'unindexed_reasons_json' => ['remote_only_face'],
                'searchable' => false,
                'indexed_at' => now(),
                'provider_payload_json' => $remoteFace,
            ]);

            $remoteOnlyRecordsCreated++;
        }

        $remoteFaceIds = $remoteFaces->keys()->all();
        $localOnlyRecords = $localActiveRecords
            ->filter(fn (FaceSearchProviderRecord $record): bool => ! in_array($record->face_id, $remoteFaceIds, true));

        foreach ($localOnlyRecords as $record) {
            $payload = is_array($record->provider_payload_json) ? $record->provider_payload_json : [];
            $record->forceFill([
                'provider_payload_json' => [
                    ...$payload,
                    'reconciliation' => [
                        'status' => 'local_only_soft_deleted',
                        'reconciled_at' => now()->toIso8601String(),
                    ],
                ],
            ])->save();
            $record->delete();
        }

        return [
            'backend_key' => $this->key(),
            'collection_id' => $collectionId,
            'remote_face_count' => $remoteFaces->count(),
            'local_face_count_before' => $localActiveRecords->count(),
            'matched_records' => $matchedRecords,
            'restored_records' => $restoredRecords,
            'remote_only_records_created' => $remoteOnlyRecordsCreated,
            'local_only_records_soft_deleted' => $localOnlyRecords->count(),
        ];
    }

    public function deleteEventBackend(Event $event, EventFaceSearchSetting $settings): void
    {
        $collectionId = is_string($settings->aws_collection_id) ? trim($settings->aws_collection_id) : '';

        if ($collectionId !== '') {
            $client = $this->rekognitionClient($settings, 'query');

            try {
                $this->callAws(
                    operation: 'query',
                    callback: fn () => $client->deleteCollection([
                        'CollectionId' => $collectionId,
                    ]),
                    context: [
                        'event_id' => $event->id,
                        'collection_id' => $collectionId,
                        'aws_operation' => 'DeleteCollection',
                    ],
                    suppressAwsErrorCodes: ['ResourceNotFoundException'],
                );
            } catch (AwsException $exception) {
                if (($exception->getAwsErrorCode() ?? null) !== 'ResourceNotFoundException') {
                    throw $exception;
                }
            }
        }

        FaceSearchProviderRecord::query()
            ->where('event_id', $event->id)
            ->where('backend_key', $this->key())
            ->when($collectionId !== '', fn ($query) => $query->where('collection_id', $collectionId))
            ->whereNull('deleted_at')
            ->get()
            ->each(function (FaceSearchProviderRecord $record): void {
                $payload = is_array($record->provider_payload_json) ? $record->provider_payload_json : [];
                $record->forceFill([
                    'provider_payload_json' => [
                        ...$payload,
                        'collection_deleted' => [
                            'deleted_at' => now()->toIso8601String(),
                        ],
                    ],
                ])->save();
                $record->delete();
            });

        $settings->forceFill([
            'aws_collection_id' => null,
            'aws_collection_arn' => null,
            'aws_face_model_version' => null,
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function healthFailure(array $base, string $status, string $failedCheck, Throwable $exception): array
    {
        return [
            ...$base,
            'status' => $status,
            'checks' => [
                ...$base['checks'],
                $failedCheck => 'failed',
            ],
            'error_code' => $exception instanceof AwsException ? $exception->getAwsErrorCode() : $exception::class,
            'error_message' => $exception instanceof AwsException
                ? $exception->getAwsErrorMessage()
                : $exception->getMessage(),
        ];
    }

    private function healthStatusFor(Throwable $exception): string
    {
        if ($exception instanceof AwsException && $this->isAccessDenied($exception)) {
            return 'misconfigured';
        }

        return 'provider_unavailable';
    }

    /**
     * @return array<int, string>
     */
    private function requiredActions(): array
    {
        return [
            'sts:GetCallerIdentity',
            'rekognition:CreateCollection',
            'rekognition:CreateUser',
            'rekognition:DescribeCollection',
            'rekognition:IndexFaces',
            'rekognition:AssociateFaces',
            'rekognition:SearchFacesByImage',
            'rekognition:SearchUsersByImage',
            'rekognition:ListFaces',
            'rekognition:DeleteFaces',
            'rekognition:DeleteCollection',
        ];
    }

    private function rekognitionClient(EventFaceSearchSetting $settings, string $profile): RekognitionClient
    {
        return $this->clients->makeRekognitionClient($profile, [
            'region' => $this->resolveRegion($settings),
        ]);
    }

    private function resolveRegion(EventFaceSearchSetting $settings): string
    {
        return $settings->aws_region ?: (string) config('face_search.providers.aws_rekognition.region', 'eu-central-1');
    }

    private function resolveCollectionId(Event $event, EventFaceSearchSetting $settings): string
    {
        if (is_string($settings->aws_collection_id) && trim($settings->aws_collection_id) !== '') {
            return trim($settings->aws_collection_id);
        }

        return "eventovivo-face-search-event-{$event->id}";
    }

    private function resolveMaxFaces(EventFaceSearchSetting $settings): int
    {
        return max(1, min(100, (int) $settings->aws_max_faces_per_image));
    }

    /**
     * @return array<int, string>
     */
    private function resolveDetectionAttributes(EventFaceSearchSetting $settings): array
    {
        $attributes = array_values(array_filter((array) $settings->aws_detection_attributes_json, fn (mixed $value): bool => is_string($value) && trim($value) !== ''));

        return $attributes !== [] ? $attributes : ['DEFAULT', 'FACE_OCCLUDED'];
    }

    private function externalImageId(EventMedia $media, string $binary): string
    {
        return sprintf(
            'evt:%d:media:%d:rev:%s',
            $media->event_id,
            $media->id,
            substr(hash('sha256', $binary), 0, 12),
        );
    }

    private function cleanupExistingProviderRecords(RekognitionClient $client, EventMedia $media, string $collectionId): void
    {
        $existingFaceIds = FaceSearchProviderRecord::query()
            ->where('event_media_id', $media->id)
            ->where('backend_key', $this->key())
            ->whereNotNull('face_id')
            ->pluck('face_id')
            ->filter(fn (mixed $faceId): bool => is_string($faceId) && $faceId !== '')
            ->values()
            ->all();

        if ($existingFaceIds !== []) {
            $this->callAws(
                operation: 'index',
                callback: fn () => $client->deleteFaces([
                    'CollectionId' => $collectionId,
                    'FaceIds' => $existingFaceIds,
                ]),
                context: [
                    'event_id' => $media->event_id,
                    'event_media_id' => $media->id,
                    'collection_id' => $collectionId,
                    'aws_operation' => 'DeleteFaces',
                    'face_count' => count($existingFaceIds),
                ],
            );
        }

        FaceSearchProviderRecord::query()
            ->where('event_media_id', $media->id)
            ->where('backend_key', $this->key())
            ->delete();
    }

    /**
     * @param array<string, mixed> $facePayload
     * @param array<string, mixed> $faceDetail
     */
    private function mapDetectedFace(array $facePayload, array $faceDetail, int $imageWidth, int $imageHeight): DetectedFaceData
    {
        $box = (array) ($faceDetail['BoundingBox'] ?? []);
        $width = max(1, (int) round(((float) ($box['Width'] ?? 0.0)) * $imageWidth));
        $height = max(1, (int) round(((float) ($box['Height'] ?? 0.0)) * $imageHeight));

        return new DetectedFaceData(
            boundingBox: new FaceBoundingBoxData(
                x: max(0, (int) round(((float) ($box['Left'] ?? 0.0)) * $imageWidth)),
                y: max(0, (int) round(((float) ($box['Top'] ?? 0.0)) * $imageHeight)),
                width: $width,
                height: $height,
            ),
            detectionConfidence: $this->normalizePercentage($facePayload['Confidence'] ?? null) ?? 0.0,
            qualityScore: $this->composedQualityScore($facePayload, $faceDetail),
            sharpnessScore: $this->normalizePercentage(data_get($faceDetail, 'Quality.Sharpness')),
            faceAreaRatio: ($width * $height) / max(1, ($imageWidth * $imageHeight)),
            poseYaw: $this->nullableFloat(data_get($faceDetail, 'Pose.Yaw')),
            posePitch: $this->nullableFloat(data_get($faceDetail, 'Pose.Pitch')),
            poseRoll: $this->nullableFloat(data_get($faceDetail, 'Pose.Roll')),
            landmarks: $this->mapLandmarks((array) ($faceDetail['Landmarks'] ?? []), $imageWidth, $imageHeight),
            providerPayload: [
                'face' => $facePayload,
                'detail' => $faceDetail,
            ],
        );
    }

    /**
     * @param array<int, array<string, mixed>> $landmarks
     * @return array<int, array{x:int, y:int, type?:string}>
     */
    private function mapLandmarks(array $landmarks, int $imageWidth, int $imageHeight): array
    {
        return collect($landmarks)
            ->map(function (mixed $landmark) use ($imageWidth, $imageHeight): ?array {
                if (! is_array($landmark)) {
                    return null;
                }

                return array_filter([
                    'x' => max(0, (int) round(((float) ($landmark['X'] ?? 0.0)) * $imageWidth)),
                    'y' => max(0, (int) round(((float) ($landmark['Y'] ?? 0.0)) * $imageHeight)),
                    'type' => is_string($landmark['Type'] ?? null) ? $landmark['Type'] : null,
                ], static fn (mixed $value): bool => $value !== null);
            })
            ->filter()
            ->values()
            ->all();
    }

    private function composedQualityScore(array $facePayload, array $faceDetail): float
    {
        $scores = array_filter([
            $this->normalizePercentage($facePayload['Confidence'] ?? null),
            $this->normalizePercentage(data_get($faceDetail, 'Quality.Sharpness')),
            $this->normalizePercentage(data_get($faceDetail, 'Quality.Brightness')),
        ], static fn (?float $value): bool => $value !== null);

        if ($scores === []) {
            return 0.0;
        }

        return min($scores);
    }

    private function normalizePercentage(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max(0.0, min(1.0, ((float) $value) / 100));
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @return array{x:int,y:int,width:int,height:int}
     */
    private function boundingBoxArray(DetectedFaceData $face): array
    {
        return [
            'x' => $face->boundingBox->x,
            'y' => $face->boundingBox->y,
            'width' => $face->boundingBox->width,
            'height' => $face->boundingBox->height,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(mixed $result): array
    {
        if (is_array($result)) {
            return $result;
        }

        if (is_object($result) && method_exists($result, 'toArray')) {
            /** @var array<string, mixed> $array */
            $array = $result->toArray();

            return $array;
        }

        return [];
    }

    private function isResourceAlreadyExists(AwsException $exception): bool
    {
        return $exception->getAwsErrorCode() === 'ResourceAlreadyExistsException';
    }

    private function isAccessDenied(AwsException $exception): bool
    {
        return in_array($exception->getAwsErrorCode(), [
            'AccessDeniedException',
            'UnrecognizedClientException',
            'InvalidSignatureException',
        ], true);
    }

    private function isNoFaceFound(AwsException $exception): bool
    {
        $code = $exception->getAwsErrorCode();
        $message = strtolower($exception->getAwsErrorMessage() ?? $exception->getMessage());

        return $code === 'InvalidParameterException'
            && str_contains($message, 'no face');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listAllFaces(RekognitionClient $client, string $collectionId): array
    {
        $faces = [];
        $nextToken = null;

        do {
            $payload = [
                'CollectionId' => $collectionId,
                'MaxResults' => 4096,
            ];

            if (is_string($nextToken) && $nextToken !== '') {
                $payload['NextToken'] = $nextToken;
            }

            $response = $this->toArray($this->callAws(
                operation: 'query',
                callback: fn () => $client->listFaces($payload),
            ));

            foreach ((array) ($response['Faces'] ?? []) as $face) {
                if (is_array($face)) {
                    $faces[] = $face;
                }
            }

            $nextToken = is_string($response['NextToken'] ?? null) ? $response['NextToken'] : null;
        } while ($nextToken !== null);

        return $faces;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function listFaceBoundingBoxArray(array $face): ?array
    {
        $boundingBox = (array) ($face['BoundingBox'] ?? []);

        if ($boundingBox === []) {
            return null;
        }

        return [
            'left' => isset($boundingBox['Left']) ? (float) $boundingBox['Left'] : null,
            'top' => isset($boundingBox['Top']) ? (float) $boundingBox['Top'] : null,
            'width' => isset($boundingBox['Width']) ? (float) $boundingBox['Width'] : null,
            'height' => isset($boundingBox['Height']) ? (float) $boundingBox['Height'] : null,
        ];
    }

    private function resolveMediaIdFromExternalImageId(Event $event, mixed $externalImageId): ?int
    {
        if (! is_string($externalImageId) || $externalImageId === '') {
            return null;
        }

        if (! preg_match('/^evt:(\d+):media:(\d+):rev:/', $externalImageId, $matches)) {
            return null;
        }

        $eventId = (int) ($matches[1] ?? 0);
        $mediaId = (int) ($matches[2] ?? 0);

        if ($eventId !== $event->id || $mediaId <= 0) {
            return null;
        }

        $exists = EventMedia::query()
            ->where('event_id', $event->id)
            ->whereKey($mediaId)
            ->exists();

        return $exists ? $mediaId : null;
    }

    private function circuitBreaker(): ProviderCircuitBreaker
    {
        return $this->circuitBreaker ?? app(ProviderCircuitBreaker::class);
    }

    private function circuitBreakerFailureThreshold(): int
    {
        return (int) config('face_search.providers.aws_rekognition.circuit_breaker.failure_threshold', 0);
    }

    private function circuitBreakerOpenSeconds(): int
    {
        return (int) config('face_search.providers.aws_rekognition.circuit_breaker.open_seconds', 0);
    }

    private function circuitScope(string $operation): string
    {
        return "face-search:aws-rekognition:{$operation}";
    }

    /**
     * @param array<int, string> $suppressAwsErrorCodes
     */
    private function callAws(
        string $operation,
        callable $callback,
        array $context = [],
        array $suppressAwsErrorCodes = [],
    ): mixed
    {
        try {
            return $this->circuitBreaker()->call(
                scope: $this->circuitScope($operation),
                failureThreshold: $this->circuitBreakerFailureThreshold(),
                openSeconds: $this->circuitBreakerOpenSeconds(),
                callback: $callback,
            );
        } catch (Throwable $exception) {
            if (! $this->shouldSuppressAwsFailureTelemetry($exception, $suppressAwsErrorCodes)) {
                $this->telemetry()->recordAwsOperationFailed($operation, $exception, $context);
            }

            throw $exception;
        }
    }

    /**
     * @param array<int, string> $suppressAwsErrorCodes
     */
    private function shouldSuppressAwsFailureTelemetry(Throwable $exception, array $suppressAwsErrorCodes): bool
    {
        if (! $exception instanceof AwsException) {
            return false;
        }

        $code = $exception->getAwsErrorCode();

        return is_string($code) && in_array($code, $suppressAwsErrorCodes, true);
    }

    private function telemetry(): FaceSearchTelemetryService
    {
        return $this->telemetry ?? app(FaceSearchTelemetryService::class);
    }
}
