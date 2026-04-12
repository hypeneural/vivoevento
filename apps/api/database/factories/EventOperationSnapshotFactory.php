<?php

namespace Database\Factories;

use App\Modules\EventOperations\Models\EventOperationSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventOperationSnapshotFactory extends Factory
{
    protected $model = EventOperationSnapshot::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'schema_version' => 1,
            'snapshot_version' => fake()->numberBetween(1, 1000),
            'latest_event_sequence' => fake()->numberBetween(1, 1000),
            'timeline_cursor' => 'evt_' . str_pad((string) fake()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'snapshot_json' => [
                'health' => [
                    'status' => fake()->randomElement(['healthy', 'attention', 'risk']),
                ],
                'stations' => [
                    [
                        'station_key' => 'intake',
                        'queue_depth' => fake()->numberBetween(0, 8),
                    ],
                ],
            ],
            'server_time' => now(),
            'updated_at' => now(),
        ];
    }
}
