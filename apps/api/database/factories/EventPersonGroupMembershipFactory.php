<?php

namespace Database\Factories;

use App\Modules\EventPeople\Models\EventPersonGroupMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventPersonGroupMembershipFactory extends Factory
{
    protected $model = EventPersonGroupMembership::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'event_person_group_id' => EventPersonGroupFactory::new()->state(fn (array $attributes) => [
                'event_id' => $attributes['event_id'],
            ]),
            'event_person_id' => EventPersonFactory::new()->state(fn (array $attributes) => [
                'event_id' => $attributes['event_id'],
            ]),
            'role_label' => null,
            'source' => 'manual',
            'confidence' => null,
            'status' => 'active',
        ];
    }
}
