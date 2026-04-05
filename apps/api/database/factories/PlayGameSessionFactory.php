<?php

namespace Database\Factories;

use App\Modules\Play\Enums\PlayGameSessionStatus;
use App\Modules\Play\Models\PlayGameSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PlayGameSessionFactory extends Factory
{
    protected $model = PlayGameSession::class;

    public function definition(): array
    {
        return [
            'event_game_id' => PlayEventGameFactory::new(),
            'uuid' => (string) Str::uuid(),
            'player_identifier' => 'player-' . Str::lower(Str::random(8)),
            'player_name' => fake()->firstName(),
            'resume_token' => Str::random(40),
            'status' => PlayGameSessionStatus::Started->value,
            'started_at' => now(),
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(3),
            'finished_at' => null,
            'result_json' => [],
        ];
    }

    public function finished(): static
    {
        return $this->state(fn () => [
            'status' => PlayGameSessionStatus::Finished->value,
            'finished_at' => now(),
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(3),
            'result_json' => [
                'score' => 900,
                'completed' => true,
                'time_ms' => 32000,
                'moves' => 18,
            ],
        ]);
    }
}
