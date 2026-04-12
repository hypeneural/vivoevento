<?php

namespace Database\Factories;

use App\Modules\EventPeople\Models\EventPersonGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EventPersonGroupFactory extends Factory
{
    protected $model = EventPersonGroup::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'event_id' => EventFactory::new(),
            'display_name' => Str::title($name),
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)),
            'group_type' => 'custom',
            'side' => 'neutral',
            'notes' => null,
            'importance_rank' => 0,
            'status' => 'active',
        ];
    }
}
