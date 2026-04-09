<?php

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceBoundingBoxData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Services\FaceDetectionProviderInterface;
use App\Modules\FaceSearch\Services\FaceQualityGateService;
use App\Modules\FaceSearch\Services\SelfiePreflightService;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Validation\ValidationException;

it('returns the dominant face assessment for a valid selfie preflight', function () {
    $event = Event::factory()->create();
    $settings = new EventFaceSearchSetting(array_merge(
        EventFaceSearchSetting::defaultAttributes(),
        [
            'event_id' => $event->id,
            'min_face_size_px' => 100,
            'min_quality_score' => 0.70,
        ],
    ));

    $detector = new class implements FaceDetectionProviderInterface
    {
        public function detect(EventMedia $media, EventFaceSearchSetting $settings, string $binary): array
        {
            return [
                new DetectedFaceData(
                    boundingBox: new FaceBoundingBoxData(20, 20, 180, 180),
                    qualityScore: 0.91,
                    sharpnessScore: 0.88,
                    faceAreaRatio: 0.14,
                ),
            ];
        }
    };

    $service = new SelfiePreflightService($detector, app(FaceQualityGateService::class));

    $result = $service->validateForSearch($event, $settings, 'binary-image', false);

    expect($result['detected_faces_count'])->toBe(1)
        ->and($result['probe_media'])->toBeInstanceOf(EventMedia::class)
        ->and($result['face'])->toBeInstanceOf(DetectedFaceData::class)
        ->and($result['assessment']->isRejected())->toBeFalse();
});

it('fails preflight when the selfie has more than one visible person', function () {
    $event = Event::factory()->create();
    $settings = new EventFaceSearchSetting(array_merge(
        EventFaceSearchSetting::defaultAttributes(),
        ['event_id' => $event->id],
    ));

    $detector = new class implements FaceDetectionProviderInterface
    {
        public function detect(EventMedia $media, EventFaceSearchSetting $settings, string $binary): array
        {
            return [
                new DetectedFaceData(boundingBox: new FaceBoundingBoxData(10, 10, 120, 120), qualityScore: 0.90),
                new DetectedFaceData(boundingBox: new FaceBoundingBoxData(160, 10, 120, 120), qualityScore: 0.87),
            ];
        }
    };

    $service = new SelfiePreflightService($detector, app(FaceQualityGateService::class));

    expect(fn () => $service->validateForSearch($event, $settings, 'binary-image', true))
        ->toThrow(ValidationException::class, 'Envie uma selfie com apenas uma pessoa visivel. Busca por foto de grupo ainda nao faz parte desta versao.');
});

it('fails preflight when the only detected face is not dominant enough for selfie-only search', function () {
    $event = Event::factory()->create();
    $settings = new EventFaceSearchSetting(array_merge(
        EventFaceSearchSetting::defaultAttributes(),
        ['event_id' => $event->id],
    ));

    $detector = new class implements FaceDetectionProviderInterface
    {
        public function detect(EventMedia $media, EventFaceSearchSetting $settings, string $binary): array
        {
            return [
                new DetectedFaceData(
                    boundingBox: new FaceBoundingBoxData(60, 40, 130, 130),
                    qualityScore: 0.91,
                    faceAreaRatio: 0.03,
                    providerPayload: [
                        'image_width' => 1200,
                        'image_height' => 900,
                    ],
                ),
            ];
        }
    };

    $service = new SelfiePreflightService($detector, app(FaceQualityGateService::class));

    expect(fn () => $service->validateForSearch($event, $settings, 'binary-image', true))
        ->toThrow(ValidationException::class, 'Envie uma selfie mais aproximada, centralizada e com apenas uma pessoa em destaque. Busca por foto de grupo ainda nao faz parte desta versao.');
});

it('fails preflight when the only detected face is too off-center for selfie-only search', function () {
    $event = Event::factory()->create();
    $settings = new EventFaceSearchSetting(array_merge(
        EventFaceSearchSetting::defaultAttributes(),
        ['event_id' => $event->id],
    ));

    $detector = new class implements FaceDetectionProviderInterface
    {
        public function detect(EventMedia $media, EventFaceSearchSetting $settings, string $binary): array
        {
            return [
                new DetectedFaceData(
                    boundingBox: new FaceBoundingBoxData(880, 120, 180, 180),
                    qualityScore: 0.94,
                    faceAreaRatio: 0.11,
                    providerPayload: [
                        'image_width' => 1200,
                        'image_height' => 900,
                    ],
                ),
            ];
        }
    };

    $service = new SelfiePreflightService($detector, app(FaceQualityGateService::class));

    expect(fn () => $service->validateForSearch($event, $settings, 'binary-image', false))
        ->toThrow(ValidationException::class, 'Envie uma selfie mais aproximada, centralizada e com apenas uma pessoa em destaque. Busca por foto de grupo ainda nao faz parte desta versao.');
});
