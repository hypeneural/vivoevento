<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;

it('returns responsive image sources for public gallery images', function () {
    $event = Event::factory()->active()->create();

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'live', 'is_enabled' => true]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'media_type' => 'image',
        'width' => 1600,
        'height' => 1067,
    ]);

    foreach ([320, 768, 1440] as $width) {
        EventMediaVariant::query()->create([
            'event_media_id' => $media->id,
            'variant_key' => "gallery_{$width}",
            'disk' => 'public',
            'path' => "events/{$event->id}/variants/{$media->id}/gallery-{$width}.webp",
            'width' => $width,
            'height' => (int) round($width * 0.667),
            'size_bytes' => 64_000,
            'mime_type' => 'image/webp',
        ]);
    }

    $response = $this->apiGet("/public/events/{$event->slug}/gallery");

    $response->assertOk()
        ->assertJsonPath('data.0.responsive_sources.sizes', '(max-width: 640px) 50vw, (max-width: 1200px) 33vw, 25vw')
        ->assertJsonPath('data.0.responsive_sources.variants.0.variant_key', 'gallery_320')
        ->assertJsonPath('data.0.responsive_sources.variants.0.width', 320)
        ->assertJsonPath('data.0.responsive_sources.variants.1.width', 768)
        ->assertJsonPath('data.0.responsive_sources.variants.2.width', 1440);

    expect($response->json('data.0.responsive_sources.srcset'))
        ->toContain('gallery-320.webp 320w')
        ->toContain('gallery-768.webp 768w')
        ->toContain('gallery-1440.webp 1440w');
});

it('returns responsive poster sources for public gallery videos without treating video as photo lightbox content', function () {
    $event = Event::factory()->active()->create();

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'live', 'is_enabled' => true]);

    $video = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'width' => 1920,
        'height' => 1080,
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $video->id,
        'variant_key' => 'wall_video_poster',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$video->id}/poster.webp",
        'width' => 1280,
        'height' => 720,
        'size_bytes' => 80_000,
        'mime_type' => 'image/webp',
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $video->id,
        'variant_key' => 'wall_video_720p',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$video->id}/video.mp4",
        'width' => 1280,
        'height' => 720,
        'size_bytes' => 2_400_000,
        'mime_type' => 'video/mp4',
    ]);

    $response = $this->apiGet("/public/events/{$event->slug}/gallery");

    $response->assertOk()
        ->assertJsonPath('data.0.media_type', 'video')
        ->assertJsonPath('data.0.responsive_sources.variants.0.variant_key', 'wall_video_poster')
        ->assertJsonPath('data.0.responsive_sources.variants.0.mime_type', 'image/webp');

    expect($response->json('data.0.responsive_sources.srcset'))
        ->toContain('poster.webp 1280w')
        ->not->toContain('video.mp4');
});
