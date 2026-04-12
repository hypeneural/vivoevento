<?php

use App\Modules\Events\Enums\EventType;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;

it('returns an additive public gallery experience payload with event branding', function () {
    $event = Event::factory()->active()->create([
        'title' => 'Casamento Ana e Leo',
        'event_type' => EventType::Wedding->value,
        'primary_color' => '#123456',
        'secondary_color' => '#abcdef',
        'cover_image_path' => 'events/cover.jpg',
        'logo_path' => 'events/logo.png',
    ]);

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'live', 'is_enabled' => true]);

    $response = $this->apiGet("/public/events/{$event->slug}/gallery");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('event.id', $event->id)
        ->assertJsonPath('event.title', 'Casamento Ana e Leo')
        ->assertJsonPath('event.slug', $event->slug)
        ->assertJsonPath('event.event_type', EventType::Wedding->value)
        ->assertJsonPath('event.branding.primary_color', '#123456')
        ->assertJsonPath('event.branding.secondary_color', '#abcdef')
        ->assertJsonPath('experience.version', 1)
        ->assertJsonPath('experience.model_matrix.event_type_family', 'wedding')
        ->assertJsonPath('experience.model_matrix.style_skin', 'romantic')
        ->assertJsonPath('experience.model_matrix.behavior_profile', 'light')
        ->assertJsonPath('experience.theme_key', 'event-brand')
        ->assertJsonPath('experience.layout_key', 'editorial-masonry')
        ->assertJsonPath('experience.theme_tokens.palette.button_fill', '#123456')
        ->assertJsonPath('experience.theme_tokens.palette.accent', '#abcdef')
        ->assertJsonPath('experience.media_behavior.grid.layout', 'masonry')
        ->assertJsonPath('experience.media_behavior.video.mode', 'poster_to_modal')
        ->assertJsonPath('experience.media_behavior.lightbox.photos', true)
        ->assertJsonPath('experience.media_behavior.lightbox.videos', false);

    expect($response->json('event.branding.cover_image_url'))->toContain('/storage/events/cover.jpg')
        ->and($response->json('event.branding.logo_url'))->toContain('/storage/events/logo.png');
});

it('derives corporate public galleries into row-first media behavior', function () {
    $event = Event::factory()->active()->create([
        'event_type' => EventType::Corporate->value,
    ]);

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'live', 'is_enabled' => true]);

    $response = $this->apiGet("/public/events/{$event->slug}/gallery");

    $response->assertOk()
        ->assertJsonPath('experience.model_matrix.event_type_family', 'corporate')
        ->assertJsonPath('experience.model_matrix.style_skin', 'clean')
        ->assertJsonPath('experience.model_matrix.behavior_profile', 'light')
        ->assertJsonPath('experience.layout_key', 'timeless-rows')
        ->assertJsonPath('experience.media_behavior.grid.layout', 'rows');
});
