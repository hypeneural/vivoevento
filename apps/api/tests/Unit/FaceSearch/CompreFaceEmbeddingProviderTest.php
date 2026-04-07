<?php

use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceBoundingBoxData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Services\CompreFaceEmbeddingProvider;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\Http;

it('returns compreface calculator embedding without calling the provider again', function () {
    config()->set('face_search.embedding_dimension', 3);
    config()->set('face_search.providers.compreface', [
        'provider_version' => 'compreface-rest-v1',
        'model' => 'compreface-face-v1',
        'model_snapshot' => 'compreface-face-v1',
    ]);

    Http::fake();

    $face = new DetectedFaceData(
        boundingBox: new FaceBoundingBoxData(10, 20, 120, 140),
        detectionConfidence: 0.98,
        qualityScore: 0.98,
        providerEmbedding: [0.10, -0.20, 0.30],
        providerPayload: [
            'provider' => 'compreface',
            'plugins_versions' => [
                'calculator' => 'facenet.Calculator',
            ],
        ],
    );

    $embedding = app(CompreFaceEmbeddingProvider::class)->embed(
        EventMedia::factory()->make(['id' => 123]),
        EventFaceSearchSetting::factory()->make(['provider_key' => 'compreface']),
        'crop-binary',
        $face,
    );

    expect($embedding->vector)->toBe([0.10, -0.20, 0.30])
        ->and($embedding->providerKey)->toBe('compreface')
        ->and($embedding->providerVersion)->toBe('compreface-rest-v1')
        ->and($embedding->modelKey)->toBe('compreface-face-v1')
        ->and($embedding->modelSnapshot)->toBe('compreface-face-v1')
        ->and($embedding->embeddingVersion)->toBe('compreface-rest-v1')
        ->and($embedding->rawResponse['embedding_dimension'])->toBe(3)
        ->and(data_get($embedding->rawResponse, 'provider_payload.plugins_versions.calculator'))->toBe('facenet.Calculator');

    Http::assertSentCount(0);
});

it('fails clearly when calculator did not return an embedding', function () {
    config()->set('face_search.embedding_dimension', 3);
    config()->set('face_search.providers.compreface', [
        'provider_version' => 'compreface-rest-v1',
        'model' => 'compreface-face-v1',
        'model_snapshot' => 'compreface-face-v1',
    ]);

    $face = new DetectedFaceData(
        boundingBox: new FaceBoundingBoxData(10, 20, 120, 140),
        providerEmbedding: [],
        providerPayload: [
            'provider' => 'compreface',
        ],
    );

    app(CompreFaceEmbeddingProvider::class)->embed(
        EventMedia::factory()->make(),
        EventFaceSearchSetting::factory()->make(['provider_key' => 'compreface']),
        'crop-binary',
        $face,
    );
})->throws(RuntimeException::class, 'CompreFace calculator did not return a usable embedding');

it('fails clearly when embedding dimension does not match the configured schema dimension', function () {
    config()->set('face_search.embedding_dimension', 512);
    config()->set('face_search.providers.compreface', [
        'provider_version' => 'compreface-rest-v1',
        'model' => 'compreface-face-v1',
        'model_snapshot' => 'compreface-face-v1',
    ]);

    $face = new DetectedFaceData(
        boundingBox: new FaceBoundingBoxData(10, 20, 120, 140),
        providerEmbedding: [0.10, -0.20, 0.30],
        providerPayload: [
            'provider' => 'compreface',
        ],
    );

    app(CompreFaceEmbeddingProvider::class)->embed(
        EventMedia::factory()->make(),
        EventFaceSearchSetting::factory()->make(['provider_key' => 'compreface']),
        'crop-binary',
        $face,
    );
})->throws(RuntimeException::class, 'CompreFace embedding dimension [3] does not match configured FACE_SEARCH_EMBEDDING_DIMENSION [512]');
