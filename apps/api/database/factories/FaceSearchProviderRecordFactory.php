<?php

namespace Database\Factories;

use App\Modules\FaceSearch\Models\FaceSearchProviderRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class FaceSearchProviderRecordFactory extends Factory
{
    protected $model = FaceSearchProviderRecord::class;

    public function definition(): array
    {
        $event = EventFactory::new();

        return [
            'event_id' => $event,
            'event_media_id' => EventMediaFactory::new([
                'event_id' => $event,
            ]),
            'provider_key' => 'aws_rekognition',
            'backend_key' => 'aws_rekognition',
            'collection_id' => 'eventovivo-face-search-event-' . $this->faker->numberBetween(1, 999),
            'face_id' => 'face-' . $this->faker->uuid(),
            'user_id' => null,
            'image_id' => 'image-' . $this->faker->uuid(),
            'external_image_id' => sprintf(
                'evt:%d:media:%d:rev:%s',
                $this->faker->numberBetween(1, 999),
                $this->faker->numberBetween(1, 9999),
                substr($this->faker->sha1(), 0, 12),
            ),
            'bbox_json' => ['left' => 0.1, 'top' => 0.2, 'width' => 0.3, 'height' => 0.4],
            'landmarks_json' => [['type' => 'eyeLeft', 'x' => 0.2, 'y' => 0.3]],
            'pose_json' => ['yaw' => 1.2, 'pitch' => 0.5, 'roll' => 0.1],
            'quality_json' => ['sharpness' => 95.5, 'brightness' => 82.3],
            'unindexed_reasons_json' => null,
            'searchable' => true,
            'indexed_at' => now(),
            'provider_payload_json' => ['FaceId' => 'face-' . $this->faker->uuid()],
        ];
    }
}
