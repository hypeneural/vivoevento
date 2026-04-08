<?php

namespace Database\Factories;

use App\Modules\MediaIntelligence\Models\MediaReplyPromptPreset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MediaReplyPromptPresetFactory extends Factory
{
    protected $model = MediaReplyPromptPreset::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'slug' => Str::slug($name . '-' . $this->faker->unique()->numberBetween(10, 9999)),
            'name' => Str::title($name),
            'category' => 'neutro',
            'description' => $this->faker->sentence(),
            'prompt_template' => 'Gere uma resposta curta, acolhedora e coerente com a imagem. Use no maximo 2 emojis quando fizer sentido.',
            'sort_order' => 0,
            'is_active' => true,
            'created_by' => UserFactory::new(),
        ];
    }
}
