<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Models\EventMediaFace;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Laravel\Facades\Image;

class AwsUserHighCardinalityProbeBuilder
{
    public function __construct(
        private readonly FaceSearchMediaSourceLoader $sourceLoader,
        private readonly SelfiePreflightService $preflight,
        private readonly AwsUserVectorReadinessService $readiness,
    ) {}

    /**
     * @param array<string, mixed>|null $readinessSummary
     * @return array<int, array<string, mixed>>
     */
    public function build(
        Event $event,
        EventFaceSearchSetting $settings,
        int $limit = 40,
        ?array $readinessSummary = null,
    ): array {
        $limit = max(1, $limit);
        $readinessSummary ??= $this->readiness->evaluate($event, $settings);

        $readyClusters = collect((array) ($readinessSummary['ready_clusters'] ?? []))
            ->filter(fn (mixed $cluster): bool => is_array($cluster) && ((int) ($cluster['cluster_id'] ?? 0)) > 0)
            ->sort(function (array $left, array $right): int {
                $faceCount = ((int) ($right['face_count'] ?? 0)) <=> ((int) ($left['face_count'] ?? 0));

                if ($faceCount !== 0) {
                    return $faceCount;
                }

                $mediaCount = ((int) ($right['media_count'] ?? 0)) <=> ((int) ($left['media_count'] ?? 0));

                if ($mediaCount !== 0) {
                    return $mediaCount;
                }

                return ((int) ($left['cluster_id'] ?? 0)) <=> ((int) ($right['cluster_id'] ?? 0));
            })
            ->values();

        if ($readyClusters->isEmpty()) {
            return [];
        }

        $localFaceIds = $readyClusters
            ->flatMap(fn (array $cluster): array => array_values(array_map('intval', (array) ($cluster['local_face_ids'] ?? []))))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($localFaceIds === []) {
            return [];
        }

        $facesById = EventMediaFace::query()
            ->with('media.variants')
            ->where('event_id', $event->id)
            ->whereIn('id', $localFaceIds)
            ->get()
            ->keyBy('id');

        $probes = [];

        foreach ($readyClusters as $cluster) {
            $probe = $this->buildForCluster($event, $settings, $cluster, $facesById->all());

            if ($probe === null) {
                continue;
            }

            $probes[] = $probe;

            if (count($probes) >= $limit) {
                break;
            }
        }

        return $probes;
    }

    /**
     * @param array<string, mixed> $cluster
     * @param array<int, EventMediaFace> $facesById
     * @return array<string, mixed>|null
     */
    private function buildForCluster(
        Event $event,
        EventFaceSearchSetting $settings,
        array $cluster,
        array $facesById,
    ): ?array {
        $face = collect((array) ($cluster['local_face_ids'] ?? []))
            ->map(fn (mixed $id): ?EventMediaFace => $facesById[(int) $id] ?? null)
            ->filter(fn (?EventMediaFace $face): bool => $face instanceof EventMediaFace && $face->media !== null)
            ->sort(function (EventMediaFace $left, EventMediaFace $right): int {
                $primary = ((int) $right->is_primary_face_candidate) <=> ((int) $left->is_primary_face_candidate);

                if ($primary !== 0) {
                    return $primary;
                }

                $quality = ((float) $right->quality_score) <=> ((float) $left->quality_score);

                if ($quality !== 0) {
                    return $quality;
                }

                $area = ((float) $right->face_area_ratio) <=> ((float) $left->face_area_ratio);

                if ($area !== 0) {
                    return $area;
                }

                return $left->id <=> $right->id;
            })
            ->first();

        if (! $face instanceof EventMediaFace || ! $face->media) {
            return null;
        }

        $source = $this->sourceLoader->loadImageBinary($face->media);
        $image = Image::decode($source['binary']);
        $imageWidth = $image->width();
        $imageHeight = $image->height();

        foreach ([2.0, 1.8, 1.6, 2.2] as $scaleFactor) {
            $crop = $this->cropBox($face, $imageWidth, $imageHeight, $scaleFactor);

            $croppedBinary = (string) Image::decode($source['binary'])
                ->crop($crop['width'], $crop['height'], $crop['x'], $crop['y'])
                ->encodeUsingMediaType('image/jpeg', 90);

            try {
                $this->preflight->validateForSearch($event, $settings, $croppedBinary, false);
            } catch (ValidationException) {
                continue;
            }

            $probePath = sprintf(
                'face-search-users-high-cardinality/probes/event-%d-cluster-%d-face-%d-%s.jpg',
                $event->id,
                (int) ($cluster['cluster_id'] ?? 0),
                $face->id,
                substr(hash('sha256', $croppedBinary), 0, 12),
            );

            Storage::disk('local')->put($probePath, $croppedBinary);

            return [
                'cluster_id' => (int) ($cluster['cluster_id'] ?? 0),
                'expected_user_id' => (string) ($cluster['user_id'] ?? ''),
                'expected_event_media_ids' => array_values(array_map('intval', (array) ($cluster['event_media_ids'] ?? []))),
                'expected_provider_record_ids' => array_values(array_map('intval', (array) ($cluster['provider_record_ids'] ?? []))),
                'expected_face_ids' => array_values(array_filter((array) ($cluster['face_ids'] ?? []), 'is_string')),
                'local_face_id' => $face->id,
                'event_media_id' => (int) $face->event_media_id,
                'source_ref' => $source['source_ref'],
                'scale_factor' => $scaleFactor,
                'probe_path' => Storage::disk('local')->path($probePath),
            ];
        }

        return null;
    }

    /**
     * @return array{x:int,y:int,width:int,height:int}
     */
    private function cropBox(
        EventMediaFace $face,
        int $imageWidth,
        int $imageHeight,
        float $scaleFactor,
    ): array {
        $targetWidth = max($face->bbox_w, (int) round($face->bbox_w * $scaleFactor));
        $targetHeight = max($face->bbox_h, (int) round($face->bbox_h * $scaleFactor));

        $centerX = $face->bbox_x + ($face->bbox_w / 2);
        $centerY = $face->bbox_y + ($face->bbox_h / 2);

        $x = (int) round($centerX - ($targetWidth / 2));
        $y = (int) round($centerY - ($targetHeight / 2));

        $x = max(0, min($x, max(0, $imageWidth - $targetWidth)));
        $y = max(0, min($y, max(0, $imageHeight - $targetHeight)));

        $targetWidth = min($targetWidth, $imageWidth);
        $targetHeight = min($targetHeight, $imageHeight);

        return [
            'x' => $x,
            'y' => $y,
            'width' => $targetWidth,
            'height' => $targetHeight,
        ];
    }
}
