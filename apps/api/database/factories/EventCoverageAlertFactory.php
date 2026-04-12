<?php

namespace Database\Factories;

use App\Modules\EventPeople\Models\EventCoverageAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventCoverageAlertFactory extends Factory
{
    protected $model = EventCoverageAlert::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'event_coverage_target_id' => EventCoverageTargetFactory::new()->state(fn (array $attributes) => [
                'event_id' => $attributes['event_id'],
            ]),
            'alert_key' => 'alert-' . fake()->unique()->slug(2),
            'severity' => 'weak',
            'title' => fake()->sentence(3),
            'summary' => fake()->sentence(),
            'status' => 'active',
            'payload' => null,
            'last_evaluated_at' => now(),
        ];
    }
}
