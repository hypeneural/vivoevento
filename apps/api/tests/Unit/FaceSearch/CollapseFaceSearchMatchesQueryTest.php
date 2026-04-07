<?php

use App\Modules\FaceSearch\DTOs\FaceSearchMatchData;
use App\Modules\FaceSearch\Queries\CollapseFaceSearchMatchesQuery;

it('collapses multiple face matches by media and keeps best ranking signals', function () {
    $query = app(CollapseFaceSearchMatchesQuery::class);

    $results = $query->execute([
        new FaceSearchMatchData(faceId: 10, eventMediaId: 100, distance: 0.22, qualityScore: 0.66, faceAreaRatio: 0.11),
        new FaceSearchMatchData(faceId: 11, eventMediaId: 100, distance: 0.14, qualityScore: 0.82, faceAreaRatio: 0.16),
        new FaceSearchMatchData(faceId: 12, eventMediaId: 200, distance: 0.18, qualityScore: 0.91, faceAreaRatio: 0.20),
    ]);

    expect($results)->toHaveCount(2)
        ->and($results[0]['event_media_id'])->toBe(100)
        ->and($results[0]['best_distance'])->toBe(0.14)
        ->and($results[0]['best_quality_score'])->toBe(0.82)
        ->and($results[0]['best_face_area_ratio'])->toBe(0.16)
        ->and($results[0]['matched_face_ids'])->toBe([10, 11])
        ->and($results[1]['event_media_id'])->toBe(200);
});

it('prefers search priority matches over index only matches when distance ties', function () {
    $query = app(CollapseFaceSearchMatchesQuery::class);

    $results = $query->execute([
        new FaceSearchMatchData(
            faceId: 21,
            eventMediaId: 100,
            distance: 0.12,
            qualityScore: 0.95,
            faceAreaRatio: 0.18,
            qualityTier: 'index_only',
        ),
        new FaceSearchMatchData(
            faceId: 22,
            eventMediaId: 200,
            distance: 0.12,
            qualityScore: 0.72,
            faceAreaRatio: 0.09,
            qualityTier: 'search_priority',
        ),
    ]);

    expect($results[0]['event_media_id'])->toBe(200)
        ->and($results[0]['best_quality_tier'])->toBe('search_priority')
        ->and($results[1]['event_media_id'])->toBe(100)
        ->and($results[1]['best_quality_tier'])->toBe('index_only');
});
