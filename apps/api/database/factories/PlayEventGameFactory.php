<?php

namespace Database\Factories;

use App\Modules\Play\Models\PlayEventGame;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PlayEventGameFactory extends Factory
{
    protected $model = PlayEventGame::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'game_type_id' => PlayGameTypeFactory::new(),
            'uuid' => (string) Str::uuid(),
            'title' => 'Jogo ' . fake()->words(2, true),
            'slug' => Str::slug(fake()->words(2, true)) . '-' . Str::lower(Str::random(4)),
            'is_active' => true,
            'sort_order' => 0,
            'ranking_enabled' => true,
            'settings_json' => [],
        ];
    }
}
