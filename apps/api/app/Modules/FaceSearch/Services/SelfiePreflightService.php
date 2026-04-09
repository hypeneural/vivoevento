<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceQualityAssessmentData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Validation\ValidationException;

class SelfiePreflightService
{
    public function __construct(
        private readonly FaceDetectionProviderInterface $detector,
        private readonly FaceQualityGateService $qualityGate,
    ) {}

    /**
     * @return array{
     *   probe_media:EventMedia,
     *   detected_faces_count:int,
     *   face:DetectedFaceData,
     *   assessment:FaceQualityAssessmentData
     * }
     */
    public function validateForSearch(
        Event $event,
        EventFaceSearchSetting $settings,
        string $binary,
        bool $publicSearch = false,
    ): array {
        $probeMedia = new EventMedia([
            'event_id' => $event->id,
            'media_type' => 'image',
            'source_type' => $publicSearch ? 'public_face_search' : 'internal_face_search',
        ]);

        $detectedFaces = array_values($this->detector->detect($probeMedia, $settings, $binary));

        if ($detectedFaces === []) {
            throw ValidationException::withMessages([
                'selfie' => ['Nao encontramos um rosto valido na selfie enviada.'],
            ]);
        }

        if (count($detectedFaces) > 1) {
            throw ValidationException::withMessages([
                'selfie' => ['Envie uma selfie com apenas uma pessoa visivel. Busca por foto de grupo ainda nao faz parte desta versao.'],
            ]);
        }

        $face = $detectedFaces[0];
        $this->guardSelfieOnlyDominance($face);
        $assessment = $this->qualityGate->assess($face, $settings);

        return [
            'probe_media' => $probeMedia,
            'detected_faces_count' => count($detectedFaces),
            'face' => $face,
            'assessment' => $assessment,
        ];
    }

    private function guardSelfieOnlyDominance(DetectedFaceData $face): void
    {
        $minFaceAreaRatio = (float) config('face_search.preflight.min_selfie_face_area_ratio', 0.08);
        $maxCenterOffsetRatio = (float) config('face_search.preflight.max_selfie_center_offset_ratio', 0.22);

        if ($face->faceAreaRatio !== null && $face->faceAreaRatio < $minFaceAreaRatio) {
            throw $this->selfieOnlyValidation();
        }

        $imageWidth = data_get($face->providerPayload, 'image_width');
        $imageHeight = data_get($face->providerPayload, 'image_height');

        if (! is_numeric($imageWidth) || ! is_numeric($imageHeight) || (float) $imageWidth <= 0 || (float) $imageHeight <= 0) {
            return;
        }

        $faceCenterX = $face->boundingBox->x + ($face->boundingBox->width / 2);
        $faceCenterY = $face->boundingBox->y + ($face->boundingBox->height / 2);
        $centerOffsetX = abs($faceCenterX - ((float) $imageWidth / 2)) / (float) $imageWidth;
        $centerOffsetY = abs($faceCenterY - ((float) $imageHeight / 2)) / (float) $imageHeight;

        if (max($centerOffsetX, $centerOffsetY) > $maxCenterOffsetRatio) {
            throw $this->selfieOnlyValidation();
        }
    }

    private function selfieOnlyValidation(): ValidationException
    {
        return ValidationException::withMessages([
            'selfie' => ['Envie uma selfie mais aproximada, centralizada e com apenas uma pessoa em destaque. Busca por foto de grupo ainda nao faz parte desta versao.'],
        ]);
    }
}
