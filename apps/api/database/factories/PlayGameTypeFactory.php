<?php

namespace Database\Factories;

use App\Modules\Play\Enums\PlayGameTypeKey;
use App\Modules\Play\Models\PlayGameType;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayGameTypeFactory extends Factory
{
    protected $model = PlayGameType::class;

    public function definition(): array
    {
        return [
            'key' => PlayGameTypeKey::Memory->value,
            'name' => PlayGameTypeKey::Memory->label(),
            'description' => 'Encontre os pares usando fotos do evento.',
            'enabled' => true,
            'supports_ranking' => true,
            'supports_photo_assets' => true,
            'config_schema_json' => [],
        ];
    }

    public function puzzle(): static
    {
        return $this->state(fn () => [
            'key' => PlayGameTypeKey::Puzzle->value,
            'name' => PlayGameTypeKey::Puzzle->label(),
            'description' => 'Monte a foto do evento em formato de puzzle.',
        ]);
    }
}
