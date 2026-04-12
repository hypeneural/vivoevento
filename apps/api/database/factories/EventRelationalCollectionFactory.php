<?php

namespace Database\Factories;

use App\Modules\EventPeople\Models\EventRelationalCollection;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventRelationalCollectionFactory extends Factory
{
    protected $model = EventRelationalCollection::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'collection_key' => fake()->unique()->slug(3),
            'collection_type' => 'person_best_of',
            'source_type' => 'person',
            'display_name' => fake()->words(3, true),
            'status' => 'active',
            'visibility' => 'internal',
            'share_token' => null,
            'metadata' => null,
            'generated_at' => now(),
            'published_at' => null,
        ];
    }
}
