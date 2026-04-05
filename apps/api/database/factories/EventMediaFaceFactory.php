<?php

namespace Database\Factories;

use App\Modules\FaceSearch\Models\EventMediaFace;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventMediaFaceFactory extends Factory
{
    protected $model = EventMediaFace::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'event_media_id' => EventMediaFactory::new(),
            'face_index' => 0,
            'bbox_x' => 10,
            'bbox_y' => 10,
            'bbox_w' => 160,
            'bbox_h' => 160,
            'detection_confidence' => 0.95,
            'quality_score' => 0.88,
            'sharpness_score' => 0.82,
            'face_area_ratio' => 0.18,
            'pose_yaw' => 0.0,
            'pose_pitch' => 0.0,
            'pose_roll' => 0.0,
            'searchable' => true,
            'crop_disk' => 'ai-private',
            'crop_path' => 'events/1/faces/1/face-0.webp',
            'embedding_model_key' => 'face-embedding-foundation-v1',
            'embedding_version' => 'foundation-v1',
            'vector_store_key' => 'pgvector',
            'vector_ref' => null,
            'face_hash' => 'face_hash_example',
            'is_primary_face_candidate' => true,
            'embedding' => '[0.1,0.2,0.3]',
        ];
    }
}
