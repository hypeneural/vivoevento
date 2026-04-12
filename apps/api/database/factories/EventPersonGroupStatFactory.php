<?php

namespace Database\Factories;

use App\Modules\EventPeople\Models\EventPersonGroupStat;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventPersonGroupStatFactory extends Factory
{
    protected $model = EventPersonGroupStat::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'event_person_group_id' => EventPersonGroupFactory::new()->state(fn (array $attributes) => [
                'event_id' => $attributes['event_id'],
            ]),
            'member_count' => 0,
            'people_with_primary_photo_count' => 0,
            'people_with_media_count' => 0,
            'projected_at' => now(),
        ];
    }
}
