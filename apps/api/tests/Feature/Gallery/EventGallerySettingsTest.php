<?php

use App\Modules\Events\Enums\EventType;
use App\Modules\Events\Models\Event;
use App\Modules\Gallery\Support\GalleryBuilderSchemaRegistry;

it('returns gallery builder settings for the event and auto-creates defaults when missing', function () {
    [, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Galeria Builder Default',
        'event_type' => EventType::Wedding->value,
        'primary_color' => '#112233',
        'secondary_color' => '#445566',
    ]);

    $response = $this->apiGet("/events/{$event->id}/gallery/settings");

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.settings.event_id', $event->id)
        ->assertJsonPath('data.settings.is_enabled', true)
        ->assertJsonPath('data.settings.event_type_family', 'wedding')
        ->assertJsonPath('data.settings.style_skin', 'romantic')
        ->assertJsonPath('data.settings.behavior_profile', 'light')
        ->assertJsonPath('data.settings.theme_key', 'event-brand')
        ->assertJsonPath('data.settings.layout_key', 'editorial-masonry')
        ->assertJsonPath('data.settings.theme_tokens.palette.button_fill', '#112233')
        ->assertJsonPath('data.settings.theme_tokens.palette.accent', '#445566')
        ->assertJsonPath('data.settings.page_schema.presence_rules.require_preview_before_publish', true)
        ->assertJsonPath('data.settings.media_behavior.video.mode', 'poster_to_modal')
        ->assertJsonPath('data.mobile_budget.percentile', 75);

    $this->assertDatabaseHas('event_gallery_settings', [
        'event_id' => $event->id,
        'theme_key' => 'event-brand',
        'layout_key' => 'editorial-masonry',
    ]);
});

it('updates gallery builder settings using split theme page and media layers', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Galeria Builder Update',
        'primary_color' => '#123456',
        'secondary_color' => '#654321',
    ]);

    $experience = app(GalleryBuilderSchemaRegistry::class)->baseExperience();

    $response = $this->apiPatch("/events/{$event->id}/gallery/settings", [
        'is_enabled' => true,
        'event_type_family' => 'quince',
        'style_skin' => 'modern',
        'behavior_profile' => 'live',
        'theme_key' => 'quince-glam',
        'layout_key' => 'live-stream',
        'theme_tokens' => array_replace_recursive($experience['theme_tokens'], [
            'palette' => [
                'accent' => '#ec4899',
                'button_fill' => '#db2777',
            ],
        ]),
        'page_schema' => array_replace_recursive($experience['page_schema'], [
            'block_order' => ['hero', 'gallery_stream', 'banner_strip', 'footer_brand'],
            'blocks' => [
                'hero' => [
                    'enabled' => true,
                    'variant' => 'quince',
                    'show_logo' => true,
                    'show_face_search_cta' => false,
                ],
                'banner_strip' => [
                    'enabled' => true,
                    'positions' => ['after_12', 'after_24'],
                ],
            ],
        ]),
        'media_behavior' => array_replace_recursive($experience['media_behavior'], [
            'grid' => [
                'layout' => 'masonry',
                'density' => 'immersive',
            ],
            'video' => [
                'mode' => 'inline_preview',
                'allow_inline_preview' => true,
            ],
            'interstitials' => [
                'enabled' => true,
                'policy' => 'sponsors',
                'max_per_24_items' => 1,
            ],
        ]),
    ]);

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.settings.event_type_family', 'quince')
        ->assertJsonPath('data.settings.style_skin', 'modern')
        ->assertJsonPath('data.settings.behavior_profile', 'live')
        ->assertJsonPath('data.settings.theme_key', 'quince-glam')
        ->assertJsonPath('data.settings.layout_key', 'live-stream')
        ->assertJsonPath('data.settings.theme_tokens.palette.accent', '#ec4899')
        ->assertJsonPath('data.settings.page_schema.blocks.hero.variant', 'quince')
        ->assertJsonPath('data.settings.page_schema.blocks.banner_strip.enabled', true)
        ->assertJsonPath('data.settings.media_behavior.video.mode', 'inline_preview')
        ->assertJsonPath('data.settings.media_behavior.interstitials.policy', 'sponsors')
        ->assertJsonPath('data.settings.updated_by', $user->id);

    $this->assertDatabaseHas('event_gallery_settings', [
        'event_id' => $event->id,
        'event_type_family' => 'quince',
        'style_skin' => 'modern',
        'behavior_profile' => 'live',
        'theme_key' => 'quince-glam',
        'layout_key' => 'live-stream',
        'updated_by' => $user->id,
    ]);
});
