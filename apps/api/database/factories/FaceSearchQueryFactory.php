<?php

namespace Database\Factories;

use App\Modules\FaceSearch\Enums\FaceSearchQueryStatus;
use App\Modules\FaceSearch\Models\FaceSearchQuery;
use Illuminate\Database\Eloquent\Factories\Factory;

class FaceSearchQueryFactory extends Factory
{
    protected $model = FaceSearchQuery::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'event_face_search_request_id' => null,
            'backend_key' => 'aws_rekognition',
            'fallback_backend_key' => 'local_pgvector',
            'routing_policy' => 'aws_primary_local_fallback',
            'status' => FaceSearchQueryStatus::Completed,
            'query_media_path' => 'tmp/face-search/selfie.jpg',
            'query_face_bbox_json' => ['x' => 10, 'y' => 20, 'w' => 180, 'h' => 180],
            'result_count' => 5,
            'error_code' => null,
            'error_message' => null,
            'provider_payload_json' => ['SearchedFaceConfidence' => 99.1],
            'started_at' => now(),
            'finished_at' => now()->addSecond(),
        ];
    }
}
