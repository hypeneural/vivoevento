<?php

namespace Database\Factories;

use App\Modules\EventPeople\Models\EventMustHavePair;
use App\Modules\EventPeople\Support\PersonPairKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventMustHavePairFactory extends Factory
{
    protected $model = EventMustHavePair::class;

    public function definition(): array
    {
        $personAId = EventPersonFactory::new();
        $personBId = EventPersonFactory::new();

        return [
            'event_id' => EventFactory::new(),
            'person_a_id' => $personAId,
            'person_b_id' => $personBId,
            'person_pair_key' => '1:2',
            'label' => fake()->sentence(2),
            'required_media_count' => 1,
            'importance_rank' => 80,
            'status' => 'active',
            'notes' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (EventMustHavePair $pair): void {
            if ($pair->person_a_id && $pair->person_b_id) {
                $pair->person_pair_key = PersonPairKey::make((int) $pair->person_a_id, (int) $pair->person_b_id);
            }
        })->afterCreating(function (EventMustHavePair $pair): void {
            $pair->forceFill([
                'person_pair_key' => PersonPairKey::make((int) $pair->person_a_id, (int) $pair->person_b_id),
            ])->save();
        });
    }
}
