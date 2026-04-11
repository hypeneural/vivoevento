<?php

namespace Database\Factories;

use App\Modules\EventPeople\Models\EventPersonCooccurrence;
use App\Modules\EventPeople\Support\PersonPairKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventPersonCooccurrenceFactory extends Factory
{
    protected $model = EventPersonCooccurrence::class;

    public function definition(): array
    {
        $personA = EventPersonFactory::new()->create();
        $personB = EventPersonFactory::new()->create([
            'event_id' => $personA->event_id,
        ]);

        return [
            'event_id' => $personA->event_id,
            'person_a_id' => $personA->id,
            'person_b_id' => $personB->id,
            'person_pair_key' => PersonPairKey::make($personA->id, $personB->id),
            'co_photo_count' => 2,
            'solo_photo_count_a' => 1,
            'solo_photo_count_b' => 1,
            'average_face_distance' => 0.15,
            'weighted_score' => 0.8,
            'last_seen_together_at' => now(),
        ];
    }
}
