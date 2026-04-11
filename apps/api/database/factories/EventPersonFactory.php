<?php

namespace Database\Factories;

use App\Modules\EventPeople\Enums\EventPersonSide;
use App\Modules\EventPeople\Enums\EventPersonStatus;
use App\Modules\EventPeople\Enums\EventPersonType;
use App\Modules\EventPeople\Models\EventPerson;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EventPersonFactory extends Factory
{
    protected $model = EventPerson::class;

    public function definition(): array
    {
        $name = fake()->name();

        return [
            'event_id' => EventFactory::new(),
            'display_name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)),
            'type' => EventPersonType::Guest->value,
            'side' => EventPersonSide::Neutral->value,
            'importance_rank' => 0,
            'notes' => null,
            'status' => EventPersonStatus::Active->value,
        ];
    }
}
