<?php

namespace Database\Factories;

use App\Modules\EventPeople\Models\EventCoverageTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventCoverageTargetFactory extends Factory
{
    protected $model = EventCoverageTarget::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'key' => 'target-' . fake()->unique()->slug(2),
            'label' => fake()->sentence(2),
            'target_type' => 'person',
            'person_a_id' => EventPersonFactory::new()->state(fn (array $attributes) => [
                'event_id' => $attributes['event_id'],
            ]),
            'person_b_id' => null,
            'event_person_group_id' => null,
            'required_media_count' => 1,
            'required_published_media_count' => 0,
            'importance_rank' => 50,
            'source' => 'manual',
            'status' => 'active',
            'metadata' => null,
            'last_evaluated_at' => null,
        ];
    }
}
