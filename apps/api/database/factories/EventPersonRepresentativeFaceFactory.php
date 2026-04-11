<?php

namespace Database\Factories;

use App\Modules\EventPeople\Models\EventPersonRepresentativeFace;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventPersonRepresentativeFaceFactory extends Factory
{
    protected $model = EventPersonRepresentativeFace::class;

    public function definition(): array
    {
        $person = EventPersonFactory::new()->create();
        $media = EventMediaFactory::new()->create([
            'event_id' => $person->event_id,
        ]);
        $face = EventMediaFaceFactory::new()->create([
            'event_id' => $person->event_id,
            'event_media_id' => $media->id,
        ]);

        return [
            'event_id' => $person->event_id,
            'event_person_id' => $person->id,
            'event_media_face_id' => $face->id,
            'rank_score' => 90.0,
            'quality_score' => 0.9,
            'pose_bucket' => 'center-level',
            'context_hash' => sha1('media:' . $face->event_media_id . '|pose:center-level'),
            'sync_status' => 'pending',
            'last_synced_at' => null,
            'sync_payload' => null,
        ];
    }
}
