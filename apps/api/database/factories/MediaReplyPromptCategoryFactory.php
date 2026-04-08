<?php

namespace Database\Factories;

use App\Modules\MediaIntelligence\Models\MediaReplyPromptCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaReplyPromptCategory>
 */
class MediaReplyPromptCategoryFactory extends Factory
{
    protected $model = MediaReplyPromptCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name),
            'name' => Str::title($name),
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
