<?php

namespace Database\Factories;

use App\Modules\FaceSearch\Models\EventFaceSearchRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFaceSearchRequestFactory extends Factory
{
    protected $model = EventFaceSearchRequest::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'requester_type' => 'guest',
            'requester_user_id' => null,
            'status' => 'completed',
            'consent_version' => 'v1',
            'selfie_storage_strategy' => 'memory_only',
            'faces_detected' => 1,
            'query_face_quality_score' => 0.85,
            'top_k' => 25,
            'best_distance' => 0.12,
            'result_photo_ids_json' => [1, 2, 3],
            'expires_at' => now()->addDay(),
        ];
    }
}
