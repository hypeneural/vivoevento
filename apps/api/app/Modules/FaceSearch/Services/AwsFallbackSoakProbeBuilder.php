<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Models\FaceSearchProviderRecord;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Laravel\Facades\Image;

class AwsFallbackSoakProbeBuilder
{
    public function __construct(
        private readonly FaceSearchMediaSourceLoader $sourceLoader,
        private readonly FaceDetectionProviderInterface $detector,
        private readonly SelfiePreflightService $preflight,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function build(Event $event, EventFaceSearchSetting $settings, int $limit = 2): array
    {
        $limit = max(1, $limit);

        $eventMediaIds = FaceSearchProviderRecord::query()
            ->where('event_id', $event->id)
            ->where('backend_key', 'aws_rekognition')
            ->where('searchable', true)
            ->whereNotNull('event_media_id')
            ->distinct()
            ->orderBy('event_media_id')
            ->pluck('event_media_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($eventMediaIds === []) {
            $eventMediaIds = EventMedia::query()
                ->where('event_id', $event->id)
                ->where('media_type', 'image')
                ->orderBy('id')
                ->limit($limit)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
        }

        $mediaItems = EventMedia::query()
            ->with('variants')
            ->whereIn('id', $eventMediaIds)
            ->orderBy('id')
            ->get();

        $probes = [];

        foreach ($mediaItems as $media) {
            $probe = $this->buildForMedia($event, $settings, $media);

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
     * @return array<string, mixed>|null
     */
    private function buildForMedia(Event $event, EventFaceSearchSetting $settings, EventMedia $media): ?array
    {
        $source = $this->sourceLoader->loadImageBinary($media);
        $faces = array_values($this->detector->detect($media, $settings, $source['binary']));
        $primaryFace = $this->primaryFace($faces);

        if (! $primaryFace instanceof DetectedFaceData) {
            return null;
        }

        $image = Image::decode($source['binary']);
        $imageWidth = $image->width();
        $imageHeight = $image->height();

        foreach ([2.0, 1.8, 1.6, 2.2] as $scaleFactor) {
            $crop = $this->cropBox($primaryFace, $imageWidth, $imageHeight, $scaleFactor);

            $croppedBinary = (string) Image::decode($source['binary'])
                ->crop($crop['width'], $crop['height'], $crop['x'], $crop['y'])
                ->encodeUsingMediaType('image/jpeg', 90);

            try {
                $this->preflight->validateForSearch($event, $settings, $croppedBinary, false);
            } catch (ValidationException) {
                continue;
            }

            $probePath = sprintf(
                'face-search-soak/probes/event-%d-media-%d-%s.jpg',
                $event->id,
                $media->id,
                substr(hash('sha256', $croppedBinary), 0, 12),
            );

            Storage::disk('local')->put($probePath, $croppedBinary);

            return [
                'event_media_id' => $media->id,
                'source_ref' => $source['source_ref'],
                'scale_factor' => $scaleFactor,
                'probe_path' => Storage::disk('local')->path($probePath),
            ];
        }

        return null;
    }

    /**
     * @param array<int, DetectedFaceData> $faces
     */
    private function primaryFace(array $faces): ?DetectedFaceData
    {
        if ($faces === []) {
            return null;
        }

        usort($faces, static function (DetectedFaceData $left, DetectedFaceData $right): int {
            $leftPrimary = $left->isPrimaryCandidate ? 1 : 0;
            $rightPrimary = $right->isPrimaryCandidate ? 1 : 0;

            if ($leftPrimary !== $rightPrimary) {
                return $rightPrimary <=> $leftPrimary;
            }

            $leftArea = $left->boundingBox->area();
            $rightArea = $right->boundingBox->area();

            if ($leftArea !== $rightArea) {
                return $rightArea <=> $leftArea;
            }

            return $right->qualityScore <=> $left->qualityScore;
        });

        return $faces[0] ?? null;
    }

    /**
     * @return array{x:int,y:int,width:int,height:int}
     */
    private function cropBox(
        DetectedFaceData $face,
        int $imageWidth,
        int $imageHeight,
        float $scaleFactor,
    ): array {
        $targetWidth = max(
            $face->boundingBox->width,
            (int) round($face->boundingBox->width * $scaleFactor),
        );
        $targetHeight = max(
            $face->boundingBox->height,
            (int) round($face->boundingBox->height * $scaleFactor),
        );

        $centerX = $face->boundingBox->x + ($face->boundingBox->width / 2);
        $centerY = $face->boundingBox->y + ($face->boundingBox->height / 2);

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
