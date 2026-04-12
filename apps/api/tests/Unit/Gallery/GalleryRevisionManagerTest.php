<?php

use App\Modules\Events\Models\Event;
use App\Modules\Gallery\Models\EventGallerySetting;
use App\Modules\Gallery\Support\GalleryBuilderPresetRegistry;
use App\Modules\Gallery\Support\GalleryRevisionManager;

it('creates monotonic gallery revisions from the current settings payload', function () {
    $event = Event::factory()->create([
        'primary_color' => '#111827',
        'secondary_color' => '#f97316',
    ]);

    $defaults = app(GalleryBuilderPresetRegistry::class)->defaultsForEvent($event);

    /** @var EventGallerySetting $settings */
    $settings = EventGallerySetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
        'event_type_family' => $defaults['event_type_family'],
        'style_skin' => $defaults['style_skin'],
        'behavior_profile' => $defaults['behavior_profile'],
        'theme_key' => $defaults['theme_key'],
        'layout_key' => $defaults['layout_key'],
        'theme_tokens_json' => $defaults['theme_tokens'],
        'page_schema_json' => $defaults['page_schema'],
        'media_behavior_json' => $defaults['media_behavior'],
    ]);

    $manager = app(GalleryRevisionManager::class);

    $autosave = $manager->createRevision($settings, 'autosave');
    $published = $manager->createRevision($settings, 'published');

    expect($autosave->version_number)->toBe(1)
        ->and($autosave->kind)->toBe('autosave')
        ->and($autosave->theme_key)->toBe('event-brand')
        ->and($autosave->theme_tokens_json['palette']['button_fill'])->toBe('#111827')
        ->and($published->version_number)->toBe(2)
        ->and($published->kind)->toBe('published')
        ->and($published->layout_key)->toBe($settings->layout_key);
});
