<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceQualityAssessmentData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

class AwsFaceCropIndexSourceBuilder
{
    public function __construct(
        private readonly FaceDetectionProviderInterface $detector,
        private readonly FaceQualityGateService $qualityGate,
        private readonly AwsImagePreprocessor $preprocessor,
    ) {}

    /**
     * @param array{binary:string,source_ref:string} $source
     * @return array<int, array{
     *   binary:string,
     *   width:int,
     *   height:int,
     *   size_bytes:int,
     *   mime_type:string,
     *   source_ref:string,
     *   source_kind:string,
     *   face_index:int|null,
     *   source_bbox:array{x:int,y:int,width:int,height:int}|null,
     *   local_quality_tier:string|null,
     *   local_quality_reason:string|null
     * }>
     */
    public function build(EventMedia $media, EventFaceSearchSetting $settings, array $source): array
    {
        if (! $this->shouldPreferFaceCrops($settings)) {
            return [$this->preparedSource($source)];
        }

        $detectedFaces = $this->detectFaces($media, $settings, $source['binary']);

        if ($detectedFaces === []) {
            return [$this->preparedSource($source)];
        }

        $image = Image::decode($source['binary']);
        $imageWidth = $image->width();
        $imageHeight = $image->height();
        $scaleFactor = $this->cropScaleFactor($settings);

        $inputs = collect($detectedFaces)
            ->map(function (DetectedFaceData $face, int $index) use ($settings, $source, $imageWidth, $imageHeight, $scaleFactor): ?array {
                $assessment = $this->qualityGate->assess($face, $settings);

                if ($assessment->isRejected()) {
                    return null;
                }

                $crop = $this->cropBox($face, $imageWidth, $imageHeight, $scaleFactor);
                $cropBinary = (string) Image::decode($source['binary'])
                    ->crop($crop['width'], $crop['height'], $crop['x'], $crop['y'])
                    ->encodeUsingMediaType('image/jpeg', 90);

                $prepared = $this->preprocessor->prepare($cropBinary, [
                    'max_dimension' => 1920,
                    'max_bytes' => 5_242_880,
                ]);

                return [
                    ...$prepared,
                    'source_ref' => sprintf('%s#face:%d@%s', $source['source_ref'], $index, rtrim(rtrim(sprintf('%.2F', $scaleFactor), '0'), '.')),
                    'source_kind' => 'face_crop',
                    'face_index' => $index,
                    'source_bbox' => [
                        'x' => $face->boundingBox->x,
                        'y' => $face->boundingBox->y,
                        'width' => $face->boundingBox->width,
                        'height' => $face->boundingBox->height,
                    ],
                    'local_quality_tier' => $assessment->tier->value,
                    'local_quality_reason' => $assessment->reason,
                    'sort_weight' => $this->sortWeight($face, $assessment),
                ];
            })
            ->filter()
            ->sortByDesc('sort_weight')
            ->take($this->maxFaceCrops($settings))
            ->map(function (array $input): array {
                unset($input['sort_weight']);

                return $input;
            })
            ->values()
            ->all();

        return $inputs !== [] ? $inputs : [$this->preparedSource($source)];
    }

    private function shouldPreferFaceCrops(EventFaceSearchSetting $settings): bool
    {
        if (($settings->provider_key ?? 'noop') === 'noop') {
            return false;
        }

        return (bool) $this->profile($settings)['prefer_face_crops'];
    }

    private function maxFaceCrops(EventFaceSearchSetting $settings): int
    {
        $configured = (int) ($this->profile($settings)['max_face_crops'] ?? 0);

        return max(1, min(
            max(1, (int) $settings->aws_max_faces_per_image),
            $configured > 0 ? $configured : 1,
        ));
    }

    private function cropScaleFactor(EventFaceSearchSetting $settings): float
    {
        $configured = $this->profile($settings)['crop_scale_factor'] ?? 1.8;

        return max(1.2, min(3.0, (float) $configured));
    }

    /**
     * @param array{binary:string,source_ref:string} $source
     * @return array{
     *   binary:string,
     *   width:int,
     *   height:int,
     *   size_bytes:int,
     *   mime_type:string,
     *   source_ref:string,
     *   source_kind:string,
     *   face_index:int|null,
     *   source_bbox:array{x:int,y:int,width:int,height:int}|null,
     *   local_quality_tier:string|null,
     *   local_quality_reason:string|null
     * }
     */
    private function preparedSource(array $source): array
    {
        return [
            ...$this->preprocessor->prepare($source['binary'], [
                'max_dimension' => 1920,
                'max_bytes' => 5_242_880,
            ]),
            'source_ref' => $source['source_ref'],
            'source_kind' => 'source_image',
            'face_index' => null,
            'source_bbox' => null,
            'local_quality_tier' => null,
            'local_quality_reason' => null,
        ];
    }

    /**
     * @return array<int, DetectedFaceData>
     */
    private function detectFaces(EventMedia $media, EventFaceSearchSetting $settings, string $binary): array
    {
        try {
            $faces = $this->detector->detect($media, $settings, $binary);
        } catch (Throwable) {
            return [];
        }

        usort($faces, function (DetectedFaceData $left, DetectedFaceData $right): int {
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

        return $faces;
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
        $targetWidth = max($face->boundingBox->width, (int) round($face->boundingBox->width * $scaleFactor));
        $targetHeight = max($face->boundingBox->height, (int) round($face->boundingBox->height * $scaleFactor));

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

    private function sortWeight(DetectedFaceData $face, FaceQualityAssessmentData $assessment): float
    {
        $weight = $face->boundingBox->area() + ($face->qualityScore * 1000);

        if ($face->isPrimaryCandidate) {
            $weight += 1_000_000;
        }

        if ($assessment->tier->value === 'search_priority') {
            $weight += 500_000;
        }

        return $weight;
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
}
