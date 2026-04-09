<?php

use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Services\CompreFaceDetectionProvider;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('maps compreface detection response into detected face data with calculator payload', function () {
    config()->set('face_search.providers.compreface', [
        'base_url' => 'http://compreface.test',
        'api_key' => 'test-api-key',
        'face_plugins' => 'calculator,landmarks',
        'det_prob_threshold' => '0.70',
        'status' => true,
        'timeout' => 9,
        'connect_timeout' => 3,
        'provider_version' => 'compreface-rest-v1',
        'model' => 'compreface-face-v1',
        'model_snapshot' => 'compreface-face-v1',
        'use_base64' => true,
    ]);

    Http::fake([
        'http://compreface.test/api/v1/detection/detect*' => Http::response([
            'result' => [
                [
                    'box' => [
                        'probability' => 0.9987509250640869,
                        'x_min' => 68,
                        'y_min' => 77,
                        'x_max' => 376,
                        'y_max' => 479,
                    ],
                    'landmarks' => [
                        [156, 245],
                        [277, 253],
                        [202, 311],
                    ],
                    'embedding' => [0.10, -0.20, 0.30],
                    'execution_time' => [
                        'detector' => 130.0,
                        'calculator' => 49.0,
                    ],
                ],
                [
                    'box' => [
                        'probability' => 0.95,
                        'x_min' => 420,
                        'y_min' => 100,
                        'x_max' => 520,
                        'y_max' => 220,
                    ],
                    'landmarks' => [],
                    'embedding' => [0.40, 0.50, 0.60],
                ],
            ],
            'plugins_versions' => [
                'detector' => 'facenet.FaceDetector',
                'calculator' => 'facenet.Calculator',
            ],
        ]),
    ]);

    $media = EventMedia::factory()->make([
        'id' => 123,
        'media_type' => 'image',
    ]);
    $settings = EventFaceSearchSetting::factory()->make([
        'provider_key' => 'compreface',
    ]);

    $faces = app(CompreFaceDetectionProvider::class)->detect($media, $settings, 'fake-binary');

    expect($faces)->toHaveCount(2)
        ->and($faces[0]->boundingBox->x)->toBe(68)
        ->and($faces[0]->boundingBox->y)->toBe(77)
        ->and($faces[0]->boundingBox->width)->toBe(308)
        ->and($faces[0]->boundingBox->height)->toBe(402)
        ->and($faces[0]->detectionConfidence)->toBe(0.998751)
        ->and($faces[0]->qualityScore)->toBe(0.998751)
        ->and($faces[0]->landmarks)->toBe([
            ['x' => 156, 'y' => 245],
            ['x' => 277, 'y' => 253],
            ['x' => 202, 'y' => 311],
        ])
        ->and($faces[0]->providerEmbedding)->toBe([0.10, -0.20, 0.30])
        ->and(data_get($faces[0]->providerPayload, 'plugins_versions.calculator'))->toBe('facenet.Calculator')
        ->and($faces[0]->isPrimaryCandidate)->toBeTrue()
        ->and($faces[1]->isPrimaryCandidate)->toBeFalse();

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && str_starts_with((string) $request->url(), 'http://compreface.test/api/v1/detection/detect')
            && str_contains((string) $request->url(), 'face_plugins=calculator%2Clandmarks')
            && str_contains((string) $request->url(), 'det_prob_threshold=0.70')
            && $request['file'] === base64_encode('fake-binary');
    });
});

it('uses multipart fallback when base64 is disabled in config', function () {
    config()->set('face_search.providers.compreface', [
        'base_url' => 'http://compreface.test',
        'api_key' => 'test-api-key',
        'face_plugins' => 'calculator,landmarks',
        'status' => true,
        'timeout' => 9,
        'connect_timeout' => 3,
        'use_base64' => false,
    ]);

    Http::fake([
        'http://compreface.test/api/v1/detection/detect*' => Http::response([
            'result' => [],
        ]),
    ]);

    $faces = app(CompreFaceDetectionProvider::class)->detect(
        EventMedia::factory()->make(['mime_type' => 'image/png']),
        EventFaceSearchSetting::factory()->make(['provider_key' => 'compreface']),
        'fake-binary',
    );

    expect($faces)->toBe([]);

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && str_contains((string) ($request->header('Content-Type')[0] ?? ''), 'multipart/form-data');
    });
});

it('throws a clear exception when compreface returns an unmappable face', function () {
    config()->set('face_search.providers.compreface', [
        'base_url' => 'http://compreface.test',
        'api_key' => 'test-api-key',
        'face_plugins' => 'calculator,landmarks',
        'status' => true,
        'timeout' => 9,
        'connect_timeout' => 3,
        'use_base64' => true,
    ]);

    Http::fake([
        'http://compreface.test/api/v1/detection/detect*' => Http::response([
            'result' => [
                [
                    'box' => [
                        'probability' => 0.9,
                        'x_min' => 10,
                    ],
                ],
            ],
        ]),
    ]);

    app(CompreFaceDetectionProvider::class)->detect(
        EventMedia::factory()->make(),
        EventFaceSearchSetting::factory()->make(['provider_key' => 'compreface']),
        'fake-binary',
    );
})->throws(RuntimeException::class, 'CompreFace detection response contains an invalid bounding box');

it('captures image dimensions and face area ratio when the source image can be decoded', function () {
    config()->set('face_search.providers.compreface', [
        'base_url' => 'http://compreface.test',
        'api_key' => 'test-api-key',
        'face_plugins' => 'calculator,landmarks',
        'status' => true,
        'timeout' => 9,
        'connect_timeout' => 3,
        'use_base64' => true,
    ]);

    Http::fake([
        'http://compreface.test/api/v1/detection/detect*' => Http::response([
            'result' => [
                [
                    'box' => [
                        'probability' => 0.99,
                        'x_min' => 68,
                        'y_min' => 77,
                        'x_max' => 376,
                        'y_max' => 479,
                    ],
                    'landmarks' => [],
                    'embedding' => [0.10, -0.20, 0.30],
                ],
            ],
        ]),
    ]);

    Storage::fake('public');

    $path = UploadedFile::fake()
        ->image('probe.jpg', 1200, 900)
        ->store('tmp', 'public');
    $binary = Storage::disk('public')->get($path);

    $faces = app(CompreFaceDetectionProvider::class)->detect(
        EventMedia::factory()->make(['id' => 456, 'media_type' => 'image']),
        EventFaceSearchSetting::factory()->make(['provider_key' => 'compreface']),
        (string) $binary,
    );

    expect($faces)->toHaveCount(1)
        ->and($faces[0]->faceAreaRatio)->not->toBeNull()
        ->and($faces[0]->faceAreaRatio)->toBeGreaterThan(0.11)
        ->and($faces[0]->faceAreaRatio)->toBeLessThan(0.12)
        ->and(data_get($faces[0]->providerPayload, 'image_width'))->toBe(1200)
        ->and(data_get($faces[0]->providerPayload, 'image_height'))->toBe(900);
});
