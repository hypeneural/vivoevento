<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\FaceSearch\Models\FaceSearchProviderRecord;
use Illuminate\Support\Collection;

class AwsUserVectorReadinessService
{
    /**
     * @return array{
     *   cluster_threshold:float,
     *   min_faces_per_user:int,
     *   min_yaw_spread:float,
     *   min_pitch_spread:float,
     *   matched_candidates:int,
     *   clusters_total:int,
     *   ready_clusters:array<int, array<string, mixed>>,
     *   pending_clusters:array<int, array<string, mixed>>
     * }
     */
    public function evaluate(Event $event, EventFaceSearchSetting $settings): array
    {
        $clusterThreshold = $this->clusterThreshold($settings);
        $minFacesPerUser = $this->minFacesPerUser($settings);
        $minYawSpread = $this->minYawSpread($settings);
        $minPitchSpread = $this->minPitchSpread($settings);
        $collectionId = $this->resolveCollectionId($event, $settings);
        $providerRecords = FaceSearchProviderRecord::query()
            ->where('event_id', $event->id)
            ->where('backend_key', 'aws_rekognition')
            ->where('collection_id', $collectionId)
            ->where('searchable', true)
            ->whereNotNull('face_id')
            ->whereNotNull('event_media_id')
            ->orderBy('id')
            ->get();

        $localFacesByMedia = EventMediaFace::query()
            ->where('event_id', $event->id)
            ->where('searchable', true)
            ->whereNotNull('embedding')
            ->get([
                'id',
                'event_media_id',
                'bbox_x',
                'bbox_y',
                'bbox_w',
                'bbox_h',
                'quality_score',
                'quality_tier',
                'face_area_ratio',
                'pose_yaw',
                'pose_pitch',
                'is_primary_face_candidate',
                'embedding',
            ])
            ->groupBy('event_media_id');

        $candidates = $providerRecords
            ->map(function (FaceSearchProviderRecord $record) use ($localFacesByMedia): ?array {
                /** @var Collection<int, EventMediaFace> $localFaces */
                $localFaces = $localFacesByMedia->get($record->event_media_id, collect());
                $matchedLocalFace = $this->matchLocalFace($record, $localFaces);

                if (! $matchedLocalFace instanceof EventMediaFace) {
                    return null;
                }

                $vector = $this->parseVector((string) $matchedLocalFace->embedding);

                if ($vector === []) {
                    return null;
                }

                $quality = is_array($record->quality_json) ? $record->quality_json : [];
                $pose = is_array($record->pose_json) ? $record->pose_json : [];

                return [
                    'provider_record_id' => (int) $record->id,
                    'face_id' => (string) $record->face_id,
                    'event_media_id' => (int) $record->event_media_id,
                    'local_face_id' => (int) $matchedLocalFace->id,
                    'vector' => $vector,
                    'quality_score' => $this->nullableFloat($quality['composed_quality_score'] ?? $matchedLocalFace->quality_score),
                    'quality_tier' => is_string($quality['quality_tier'] ?? null)
                        ? $quality['quality_tier']
                        : $matchedLocalFace->quality_tier,
                    'face_area_ratio' => $this->nullableFloat($quality['face_area_ratio'] ?? $matchedLocalFace->face_area_ratio),
                    'pose_yaw' => $this->nullableFloat($pose['yaw'] ?? $matchedLocalFace->pose_yaw) ?? 0.0,
                    'pose_pitch' => $this->nullableFloat($pose['pitch'] ?? $matchedLocalFace->pose_pitch) ?? 0.0,
                    'existing_user_id' => is_string($record->user_id) && trim($record->user_id) !== ''
                        ? trim($record->user_id)
                        : null,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $clusters = $this->clusterCandidates($candidates, $clusterThreshold);
        $readyClusters = [];
        $pendingClusters = [];

        foreach ($clusters as $cluster) {
            $evaluated = $this->evaluateCluster(
                event: $event,
                cluster: $cluster,
                minFacesPerUser: $minFacesPerUser,
                minYawSpread: $minYawSpread,
                minPitchSpread: $minPitchSpread,
            );

            if ($evaluated['ready']) {
                $readyClusters[] = $evaluated;

                continue;
            }

            $pendingClusters[] = $evaluated;
        }

        return [
            'cluster_threshold' => $clusterThreshold,
            'min_faces_per_user' => $minFacesPerUser,
            'min_yaw_spread' => $minYawSpread,
            'min_pitch_spread' => $minPitchSpread,
            'matched_candidates' => count($candidates),
            'clusters_total' => count($clusters),
            'ready_clusters' => $readyClusters,
            'pending_clusters' => $pendingClusters,
        ];
    }

    private function resolveCollectionId(Event $event, EventFaceSearchSetting $settings): string
    {
        if (is_string($settings->aws_collection_id) && trim($settings->aws_collection_id) !== '') {
            return trim($settings->aws_collection_id);
        }

        return "eventovivo-face-search-event-{$event->id}";
    }

    /**
     * @param Collection<int, EventMediaFace> $localFaces
     */
    private function matchLocalFace(FaceSearchProviderRecord $record, Collection $localFaces): ?EventMediaFace
    {
        if ($localFaces->isEmpty()) {
            return null;
        }

        if ($localFaces->count() === 1) {
            return $localFaces->first();
        }

        $providerBox = $this->providerBoundingBox($record);

        if ($providerBox === null) {
            return $localFaces
                ->sort(function (EventMediaFace $left, EventMediaFace $right): int {
                    $primary = ((int) $right->is_primary_face_candidate) <=> ((int) $left->is_primary_face_candidate);

                    if ($primary !== 0) {
                        return $primary;
                    }

                    return ((float) $right->quality_score) <=> ((float) $left->quality_score);
                })
                ->first();
        }

        $bestFace = null;
        $bestIou = 0.0;

        foreach ($localFaces as $localFace) {
            $localBox = [
                'x' => (float) $localFace->bbox_x,
                'y' => (float) $localFace->bbox_y,
                'width' => (float) $localFace->bbox_w,
                'height' => (float) $localFace->bbox_h,
            ];
            $iou = $this->intersectionOverUnion($providerBox, $localBox);

            if ($iou > $bestIou) {
                $bestIou = $iou;
                $bestFace = $localFace;
            }
        }

        if ($bestFace instanceof EventMediaFace && $bestIou > 0.05) {
            return $bestFace;
        }

        return null;
    }

    /**
     * @return array{x:float,y:float,width:float,height:float}|null
     */
    private function providerBoundingBox(FaceSearchProviderRecord $record): ?array
    {
        $payload = is_array($record->provider_payload_json) ? $record->provider_payload_json : [];
        $sourceBbox = data_get($payload, 'index_input.source_bbox');

        if (is_array($sourceBbox)) {
            $x = $this->nullableFloat($sourceBbox['x'] ?? null);
            $y = $this->nullableFloat($sourceBbox['y'] ?? null);
            $width = $this->nullableFloat($sourceBbox['width'] ?? null);
            $height = $this->nullableFloat($sourceBbox['height'] ?? null);

            if ($x !== null && $y !== null && $width !== null && $height !== null) {
                return [
                    'x' => $x,
                    'y' => $y,
                    'width' => $width,
                    'height' => $height,
                ];
            }
        }

        $bbox = is_array($record->bbox_json) ? $record->bbox_json : [];

        if ($bbox === []) {
            return null;
        }

        $x = $this->nullableFloat($bbox['x'] ?? null);
        $y = $this->nullableFloat($bbox['y'] ?? null);
        $width = $this->nullableFloat($bbox['width'] ?? null);
        $height = $this->nullableFloat($bbox['height'] ?? null);

        if ($x === null || $y === null || $width === null || $height === null) {
            return null;
        }

        if ($width <= 1.0 && $height <= 1.0) {
            return null;
        }

        return [
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     * @return array<int, array<string, mixed>>
     */
    private function clusterCandidates(array $candidates, float $clusterThreshold): array
    {
        $sortedCandidates = collect($candidates)
            ->sort(function (array $left, array $right): int {
                $tier = \App\Modules\FaceSearch\Enums\FaceQualityTier::rankFor($right['quality_tier'] ?? null)
                    <=> \App\Modules\FaceSearch\Enums\FaceQualityTier::rankFor($left['quality_tier'] ?? null);

                if ($tier !== 0) {
                    return $tier;
                }

                $quality = ((float) ($right['quality_score'] ?? 0.0)) <=> ((float) ($left['quality_score'] ?? 0.0));

                if ($quality !== 0) {
                    return $quality;
                }

                $area = ((float) ($right['face_area_ratio'] ?? 0.0)) <=> ((float) ($left['face_area_ratio'] ?? 0.0));

                if ($area !== 0) {
                    return $area;
                }

                return ((int) ($left['provider_record_id'] ?? 0)) <=> ((int) ($right['provider_record_id'] ?? 0));
            })
            ->values()
            ->all();

        $clusters = [];
        $nextClusterId = 1;

        foreach ($sortedCandidates as $candidate) {
            $bestClusterId = null;
            $bestDistance = INF;

            foreach ($clusters as $clusterId => $cluster) {
                $distance = $this->minDistanceToCluster(
                    (array) $candidate['vector'],
                    (array) ($cluster['exemplars'] ?? []),
                );

                if ($distance <= $clusterThreshold && $distance < $bestDistance) {
                    $bestClusterId = $clusterId;
                    $bestDistance = $distance;
                }
            }

            if ($bestClusterId === null) {
                $bestClusterId = $nextClusterId++;
                $clusters[$bestClusterId] = [
                    'cluster_id' => $bestClusterId,
                    'provider_record_ids' => [],
                    'face_ids' => [],
                    'event_media_ids' => [],
                    'local_face_ids' => [],
                    'pose_yaws' => [],
                    'pose_pitches' => [],
                    'existing_user_ids' => [],
                    'exemplars' => [],
                ];
            }

            $clusters[$bestClusterId]['provider_record_ids'][] = (int) $candidate['provider_record_id'];
            $clusters[$bestClusterId]['face_ids'][] = (string) $candidate['face_id'];
            $clusters[$bestClusterId]['event_media_ids'][] = (int) $candidate['event_media_id'];
            $clusters[$bestClusterId]['local_face_ids'][] = (int) $candidate['local_face_id'];
            $clusters[$bestClusterId]['pose_yaws'][] = (float) ($candidate['pose_yaw'] ?? 0.0);
            $clusters[$bestClusterId]['pose_pitches'][] = (float) ($candidate['pose_pitch'] ?? 0.0);

            if (is_string($candidate['existing_user_id'] ?? null) && $candidate['existing_user_id'] !== '') {
                $clusters[$bestClusterId]['existing_user_ids'][] = $candidate['existing_user_id'];
            }

            $clusters[$bestClusterId]['exemplars'][] = [
                'vector' => (array) $candidate['vector'],
                'quality_score' => (float) ($candidate['quality_score'] ?? 0.0),
                'face_area_ratio' => (float) ($candidate['face_area_ratio'] ?? 0.0),
            ];

            usort($clusters[$bestClusterId]['exemplars'], function (array $left, array $right): int {
                $quality = ((float) ($right['quality_score'] ?? 0.0)) <=> ((float) ($left['quality_score'] ?? 0.0));

                if ($quality !== 0) {
                    return $quality;
                }

                return ((float) ($right['face_area_ratio'] ?? 0.0)) <=> ((float) ($left['face_area_ratio'] ?? 0.0));
            });

            $clusters[$bestClusterId]['exemplars'] = array_slice($clusters[$bestClusterId]['exemplars'], 0, 8);
        }

        return array_values($clusters);
    }

    /**
     * @param array<string, mixed> $cluster
     * @return array<string, mixed>
     */
    private function evaluateCluster(
        Event $event,
        array $cluster,
        int $minFacesPerUser,
        float $minYawSpread,
        float $minPitchSpread,
    ): array {
        $faceIds = collect((array) ($cluster['face_ids'] ?? []))
            ->filter(fn (mixed $faceId): bool => is_string($faceId) && $faceId !== '')
            ->unique()
            ->values()
            ->all();
        $providerRecordIds = collect((array) ($cluster['provider_record_ids'] ?? []))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
        $eventMediaIds = collect((array) ($cluster['event_media_ids'] ?? []))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
        $localFaceIds = collect((array) ($cluster['local_face_ids'] ?? []))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $yawSpread = $this->spread((array) ($cluster['pose_yaws'] ?? []));
        $pitchSpread = $this->spread((array) ($cluster['pose_pitches'] ?? []));
        $reasons = [];

        if (count($faceIds) < $minFacesPerUser) {
            $reasons[] = 'insufficient_faces';
        }

        if ($yawSpread < $minYawSpread) {
            $reasons[] = 'insufficient_yaw_variation';
        }

        if ($pitchSpread < $minPitchSpread) {
            $reasons[] = 'insufficient_pitch_variation';
        }

        return [
            'cluster_id' => (int) ($cluster['cluster_id'] ?? 0),
            'user_id' => $this->resolveUserId($event, $providerRecordIds, (array) ($cluster['existing_user_ids'] ?? [])),
            'ready' => $reasons === [],
            'reason_codes' => $reasons,
            'face_count' => count($faceIds),
            'media_count' => count($eventMediaIds),
            'provider_record_ids' => $providerRecordIds,
            'face_ids' => $faceIds,
            'event_media_ids' => $eventMediaIds,
            'local_face_ids' => $localFaceIds,
            'yaw_spread' => round($yawSpread, 4),
            'pitch_spread' => round($pitchSpread, 4),
        ];
    }

    /**
     * @param array<int, string> $existingUserIds
     */
    private function resolveUserId(Event $event, array $providerRecordIds, array $existingUserIds): string
    {
        $existing = collect($existingUserIds)
            ->filter(fn (mixed $userId): bool => is_string($userId) && trim($userId) !== '')
            ->values();

        if ($existing->isNotEmpty()) {
            return (string) $existing->sort()->first();
        }

        $baseRecordId = collect($providerRecordIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->min();

        return sprintf('evt:%d:usr:pr:%d', $event->id, max(1, (int) $baseRecordId));
    }

    /**
     * @param array<int, array<string, mixed>> $exemplars
     * @param array<int, float> $vector
     */
    private function minDistanceToCluster(array $vector, array $exemplars): float
    {
        $distances = collect($exemplars)
            ->map(fn (array $exemplar): float => $this->cosineDistance($vector, (array) ($exemplar['vector'] ?? [])))
            ->values()
            ->all();

        if ($distances === []) {
            return INF;
        }

        return min($distances);
    }

    /**
     * @param array{x:float,y:float,width:float,height:float} $left
     * @param array{x:float,y:float,width:float,height:float} $right
     */
    private function intersectionOverUnion(array $left, array $right): float
    {
        $leftX2 = $left['x'] + $left['width'];
        $leftY2 = $left['y'] + $left['height'];
        $rightX2 = $right['x'] + $right['width'];
        $rightY2 = $right['y'] + $right['height'];

        $intersectionWidth = max(0.0, min($leftX2, $rightX2) - max($left['x'], $right['x']));
        $intersectionHeight = max(0.0, min($leftY2, $rightY2) - max($left['y'], $right['y']));
        $intersectionArea = $intersectionWidth * $intersectionHeight;

        if ($intersectionArea <= 0.0) {
            return 0.0;
        }

        $leftArea = max(1.0, $left['width'] * $left['height']);
        $rightArea = max(1.0, $right['width'] * $right['height']);
        $unionArea = max(1.0, $leftArea + $rightArea - $intersectionArea);

        return $intersectionArea / $unionArea;
    }

    /**
     * @return array<int, float>
     */
    private function parseVector(string $serialized): array
    {
        $trimmed = trim($serialized, "[] \t\n\r\0\x0B");

        if ($trimmed === '') {
            return [];
        }

        return array_map(
            static fn (string $value): float => (float) trim($value),
            explode(',', $trimmed),
        );
    }

    /**
     * @param array<int, float> $values
     */
    private function spread(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        $normalized = array_map(static fn (mixed $value): float => (float) $value, $values);

        return max($normalized) - min($normalized);
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function clusterThreshold(EventFaceSearchSetting $settings): float
    {
        return $this->floatProfileOverride(
            $settings,
            'user_vector_cluster_threshold',
            (float) config('face_search.providers.aws_rekognition.user_vectors.cluster_threshold', 0.35),
        );
    }

    private function minFacesPerUser(EventFaceSearchSetting $settings): int
    {
        return max(1, $this->intProfileOverride(
            $settings,
            'user_vector_min_faces_per_user',
            (int) config('face_search.providers.aws_rekognition.user_vectors.min_faces_per_user', 5),
        ));
    }

    private function minYawSpread(EventFaceSearchSetting $settings): float
    {
        return $this->floatProfileOverride(
            $settings,
            'user_vector_min_yaw_spread',
            (float) config('face_search.providers.aws_rekognition.user_vectors.min_yaw_spread', 8.0),
        );
    }

    private function minPitchSpread(EventFaceSearchSetting $settings): float
    {
        return $this->floatProfileOverride(
            $settings,
            'user_vector_min_pitch_spread',
            (float) config('face_search.providers.aws_rekognition.user_vectors.min_pitch_spread', 4.0),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(EventFaceSearchSetting $settings): array
    {
        $profileKey = is_string($settings->aws_index_profile_key) && $settings->aws_index_profile_key !== ''
            ? $settings->aws_index_profile_key
            : 'default';

        return (array) config(
            "face_search.providers.aws_rekognition.index_profiles.{$profileKey}",
            config('face_search.providers.aws_rekognition.index_profiles.default', []),
        );
    }

    private function floatProfileOverride(
        EventFaceSearchSetting $settings,
        string $key,
        float $fallback,
    ): float {
        $value = $this->profile($settings)[$key] ?? null;

        return is_numeric($value) ? (float) $value : $fallback;
    }

    private function intProfileOverride(
        EventFaceSearchSetting $settings,
        string $key,
        int $fallback,
    ): int {
        $value = $this->profile($settings)[$key] ?? null;

        return is_numeric($value) ? (int) $value : $fallback;
    }

    /**
     * @param array<int, float> $left
     * @param array<int, float> $right
     */
    private function cosineDistance(array $left, array $right): float
    {
        $count = min(count($left), count($right));

        if ($count === 0) {
            return 1.0;
        }

        $dot = 0.0;
        $normLeft = 0.0;
        $normRight = 0.0;

        for ($index = 0; $index < $count; $index++) {
            $dot += $left[$index] * $right[$index];
            $normLeft += $left[$index] ** 2;
            $normRight += $right[$index] ** 2;
        }

        if ($normLeft <= 0.0 || $normRight <= 0.0) {
            return 1.0;
        }

        $similarity = $dot / (sqrt($normLeft) * sqrt($normRight));

        return 1.0 - max(-1.0, min(1.0, $similarity));
    }
}
