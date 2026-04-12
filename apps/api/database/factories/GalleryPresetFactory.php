<?php

namespace Database\Factories;

use App\Modules\Events\Models\Event;
use App\Modules\Gallery\Models\GalleryPreset;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Users\Models\User;
use App\Modules\Gallery\Support\GalleryBuilderSchemaRegistry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GalleryPreset>
 */
class GalleryPresetFactory extends Factory
{
    protected $model = GalleryPreset::class;

    public function definition(): array
    {
        $experience = (new GalleryBuilderSchemaRegistry())->baseExperience();
        $name = 'Preset ' . fake()->unique()->words(2, true);

        return [
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'source_event_id' => Event::factory(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)),
            'description' => fake()->sentence(),
            'event_type_family' => 'wedding',
            'style_skin' => 'romantic',
            'behavior_profile' => 'light',
            'theme_key' => 'event-brand',
            'layout_key' => 'editorial-masonry',
            'theme_tokens_json' => $experience['theme_tokens'],
            'page_schema_json' => $experience['page_schema'],
            'media_behavior_json' => $experience['media_behavior'],
            'derived_preset_key' => 'wedding.romantic.light',
        ];
    }
}
