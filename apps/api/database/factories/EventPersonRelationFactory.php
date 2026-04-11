<?php

namespace Database\Factories;

use App\Modules\EventPeople\Enums\EventPersonRelationDirectionality;
use App\Modules\EventPeople\Enums\EventPersonRelationSource;
use App\Modules\EventPeople\Enums\EventPersonRelationType;
use App\Modules\EventPeople\Models\EventPersonRelation;
use App\Modules\EventPeople\Support\PersonPairKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventPersonRelationFactory extends Factory
{
    protected $model = EventPersonRelation::class;

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
            'relation_type' => EventPersonRelationType::FriendOf->value,
            'directionality' => EventPersonRelationDirectionality::Undirected->value,
            'source' => EventPersonRelationSource::Manual->value,
            'confidence' => null,
            'strength' => null,
            'is_primary' => false,
            'notes' => null,
        ];
    }
}
