<?php

namespace Database\Factories;

use App\Modules\Events\Models\EventMediaSenderBlacklist;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventMediaSenderBlacklistFactory extends Factory
{
    protected $model = EventMediaSenderBlacklist::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'identity_type' => 'phone',
            'identity_value' => fake()->numerify('55###########'),
            'normalized_phone' => fake()->numerify('55###########'),
            'reason' => fake()->sentence(),
            'expires_at' => null,
            'is_active' => true,
        ];
    }
}
