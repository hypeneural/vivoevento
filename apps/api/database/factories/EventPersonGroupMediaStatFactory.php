<?php

namespace Database\Factories;

use App\Modules\EventPeople\Models\EventPersonGroupMediaStat;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventPersonGroupMediaStatFactory extends Factory
{
    protected $model = EventPersonGroupMediaStat::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'event_person_group_id' => EventPersonGroupFactory::new()->state(fn (array $attributes) => [
                'event_id' => $attributes['event_id'],
            ]),
            'media_count' => 0,
            'published_media_count' => 0,
            'projected_at' => now(),
        ];
    }
}
