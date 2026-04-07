<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;

it('blocks public gallery access when the live module is disabled', function () {
    $event = Event::factory()->active()->create();

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'live', 'is_enabled' => false]);

    $response = $this->apiGet("/public/events/{$event->slug}/gallery");

    $response->assertStatus(404)
        ->assertJsonPath('success', false);
});

it('blocks public gallery access when the event is not active', function () {
    $event = Event::factory()->draft()->create();

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'live', 'is_enabled' => true]);

    $response = $this->apiGet("/public/events/{$event->slug}/gallery");

    $response->assertStatus(410)
        ->assertJsonPath('success', false);
});

it('returns only approved published media with optimized public gallery metadata', function () {
    $event = Event::factory()->active()->create();

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'live', 'is_enabled' => true]);

    $published = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'caption' => 'Entrada dos noivos',
        'width' => 1080,
        'height' => 1920,
        'sort_order' => 10,
        'published_at' => now()->subMinute(),
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $published->id,
        'variant_key' => 'thumb',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$published->id}/thumb.webp",
        'width' => 480,
        'height' => 480,
        'size_bytes' => 64_000,
        'mime_type' => 'image/webp',
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $published->id,
        'variant_key' => 'gallery',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$published->id}/gallery.webp",
        'width' => 1600,
        'height' => 1600,
        'size_bytes' => 320_000,
        'mime_type' => 'image/webp',
    ]);

    EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'publication_status' => PublicationStatus::Draft->value,
    ]);

    EventMedia::factory()->create([
        'event_id' => $event->id,
        'moderation_status' => ModerationStatus::Rejected->value,
        'publication_status' => PublicationStatus::Published->value,
        'published_at' => now(),
    ]);

    $response = $this->apiGet("/public/events/{$event->slug}/gallery");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $published->id)
        ->assertJsonPath('data.0.caption', 'Entrada dos noivos')
        ->assertJsonPath('data.0.orientation', 'portrait')
        ->assertJsonPath('data.0.width', 1080)
        ->assertJsonPath('data.0.height', 1920);

    expect($response->json('data.0.thumbnail_url'))->toContain("/storage/events/{$event->id}/variants/{$published->id}/thumb.webp")
        ->and($response->json('data.0.preview_url'))->toContain("/storage/events/{$event->id}/variants/{$published->id}/gallery.webp");
});
