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
                'selfie' => ['Envie uma selfie com apenas uma pessoa visivel.'],
            ]);
        }

        $face = $detectedFaces[0];
        $assessment = $this->qualityGate->assess($face, $settings);

        return [
            'probe_media' => $probeMedia,
            'detected_faces_count' => count($detectedFaces),
            'face' => $face,
            'assessment' => $assessment,
        ];
    }
}
