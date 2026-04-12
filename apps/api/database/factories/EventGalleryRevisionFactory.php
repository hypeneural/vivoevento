<?php

namespace Database\Factories;

use App\Modules\Events\Models\Event;
use App\Modules\Gallery\Models\EventGalleryRevision;
use App\Modules\Gallery\Support\GalleryBuilderSchemaRegistry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventGalleryRevision>
 */
class EventGalleryRevisionFactory extends Factory
{
    protected $model = EventGalleryRevision::class;

    public function definition(): array
    {
        $experience = (new GalleryBuilderSchemaRegistry())->baseExperience();

        return [
            'event_id' => Event::factory(),
            'version_number' => 1,
            'kind' => 'autosave',
            'event_type_family' => 'wedding',
            'style_skin' => 'romantic',
            'behavior_profile' => 'light',
            'theme_key' => 'event-brand',
            'layout_key' => 'editorial-masonry',
            'theme_tokens_json' => $experience['theme_tokens'],
            'page_schema_json' => $experience['page_schema'],
            'media_behavior_json' => $experience['media_behavior'],
            'change_summary_json' => null,
        ];
    }
}
