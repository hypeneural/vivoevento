<?php

use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceBoundingBoxData;
use App\Modules\FaceSearch\DTOs\FaceEmbeddingData;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\FaceSearch\Services\FaceQualityGateService;
use App\Modules\FaceSearch\Services\PgvectorFaceVectorStore;

it('filters faces by minimum size and quality score', function () {
    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'min_face_size_px' => 100,
        'min_quality_score' => 0.70,
    ]);

    $service = app(FaceQualityGateService::class);

    $goodFace = new DetectedFaceData(
        boundingBox: new FaceBoundingBoxData(10, 10, 160, 160),
        qualityScore: 0.88,
    );

    $tooSmallFace = new DetectedFaceData(
        boundingBox: new FaceBoundingBoxData(10, 10, 90, 90),
        qualityScore: 0.88,
    );

    $lowQualityFace = new DetectedFaceData(
        boundingBox: new FaceBoundingBoxData(10, 10, 160, 160),
        qualityScore: 0.55,
    );

    expect($service->passes($goodFace, $settings))->toBeTrue()
        ->and($service->passes($tooSmallFace, $settings))->toBeFalse()
        ->and($service->passes($lowQualityFace, $settings))->toBeFalse();
});

it('stores embeddings and searches by event using sqlite fallback', function () {
    $store = app(PgvectorFaceVectorStore::class);

    $eventA = \App\Modules\Events\Models\Event::factory()->create();
    $eventB = \App\Modules\Events\Models\Event::factory()->create();

    $mediaA = \Database\Factories\EventMediaFactory::new()->create(['event_id' => $eventA->id]);
    $mediaB = \Database\Factories\EventMediaFactory::new()->create(['event_id' => $eventB->id]);

    $faceA = \Database\Factories\EventMediaFaceFactory::new()->create([
        'event_id' => $eventA->id,
        'event_media_id' => $mediaA->id,
        'searchable' => true,
        'embedding' => null,
    ]);

    $faceB = \Database\Factories\EventMediaFaceFactory::new()->create([
        'event_id' => $eventB->id,
        'event_media_id' => $mediaB->id,
        'searchable' => true,
        'embedding' => null,
    ]);

    $store->upsert($faceA, new FaceEmbeddingData(
        vector: [0.10, 0.20, 0.30],
        modelKey: 'face-embedding-foundation-v1',
        embeddingVersion: 'foundation-v1',
    ));

    $store->upsert($faceB, new FaceEmbeddingData(
        vector: [0.90, 0.10, 0.10],
        modelKey: 'face-embedding-foundation-v1',
        embeddingVersion: 'foundation-v1',
    ));

    $matches = $store->search($eventA->id, [0.11, 0.19, 0.31], 10, 0.4, true);

    expect($matches)->toHaveCount(1)
        ->and($matches[0]->eventMediaId)->toBe($mediaA->id)
        ->and($matches[0]->distance)->toBeLessThan(0.05);

    expect(EventMediaFace::query()->find($faceA->id)?->vector_store_key)->toBe('pgvector');
});
