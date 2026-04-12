<?php

namespace Database\Factories;

use App\Modules\EventOperations\Models\EventOperationEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventOperationEventFactory extends Factory
{
    protected $model = EventOperationEvent::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'event_media_id' => null,
            'inbound_message_id' => null,
            'station_key' => fake()->randomElement([
                'intake',
                'download',
                'variants',
                'safety',
                'human_review',
                'gallery',
                'wall',
                'feedback',
            ]),
            'event_key' => fake()->randomElement([
                'media.card.arrived',
                'media.download.started',
                'media.download.completed',
                'media.moderation.pending',
                'media.published.gallery',
                'wall.health.changed',
            ]),
            'severity' => fake()->randomElement(['info', 'warning', 'critical']),
            'urgency' => fake()->randomElement(['low', 'normal', 'high']),
            'title' => fake()->sentence(3),
            'summary' => fake()->sentence(),
            'payload_json' => [
                'provider' => fake()->randomElement(['whatsapp', 'telegram', 'upload']),
                'count' => fake()->numberBetween(1, 5),
            ],
            'animation_hint' => fake()->randomElement(['pulse', 'warning', 'busy']),
            'station_load' => fake()->randomFloat(2, 0, 1),
            'queue_depth' => fake()->numberBetween(0, 12),
            'render_group' => fake()->randomElement(['intake', 'pipeline', 'publishing', 'alerts']),
            'dedupe_window_key' => fake()->optional()->lexify('win_??????'),
            'correlation_key' => fake()->lexify('corr_????????'),
            'event_sequence' => fake()->unique()->numberBetween(1, 100000),
            'occurred_at' => now()->subSeconds(fake()->numberBetween(1, 300)),
        ];
    }
}
