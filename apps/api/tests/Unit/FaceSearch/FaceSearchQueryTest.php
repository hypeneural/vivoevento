<?php

use App\Modules\FaceSearch\Enums\FaceSearchQueryStatus;
use App\Modules\FaceSearch\Models\FaceSearchQuery;

it('persists backend query audits separately from the public face search request log', function () {
    $query = \Database\Factories\FaceSearchQueryFactory::new()->create([
        'backend_key' => 'aws_rekognition',
        'status' => FaceSearchQueryStatus::Completed,
        'result_count' => 12,
        'query_face_bbox_json' => ['x' => 10, 'y' => 20, 'w' => 180, 'h' => 180],
    ]);

    $query->refresh();

    expect($query)->toBeInstanceOf(FaceSearchQuery::class)
        ->and($query->status)->toBe(FaceSearchQueryStatus::Completed)
        ->and($query->result_count)->toBe(12)
        ->and($query->query_face_bbox_json)->toBe(['x' => 10, 'y' => 20, 'w' => 180, 'h' => 180]);
});
