<?php

namespace Database\Factories;

use App\Modules\EventPeople\Models\EventCoverageTargetStat;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventCoverageTargetStatFactory extends Factory
{
    protected $model = EventCoverageTargetStat::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'event_coverage_target_id' => EventCoverageTargetFactory::new()->state(fn (array $attributes) => [
                'event_id' => $attributes['event_id'],
            ]),
            'coverage_state' => 'missing',
            'score' => 0,
            'resolved_entity_count' => 0,
            'media_count' => 0,
            'published_media_count' => 0,
            'joint_media_count' => 0,
            'people_with_primary_photo_count' => 0,
            'reason_codes' => ['sem_dados'],
            'projected_at' => now(),
        ];
    }
}
