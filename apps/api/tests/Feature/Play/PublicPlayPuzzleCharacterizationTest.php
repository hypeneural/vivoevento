<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use App\Modules\Play\Models\EventPlaySetting;
use App\Modules\Play\Models\PlayEventGame;
use App\Modules\Play\Models\PlayGameType;

function attachPuzzleVariants(EventMedia $media): void
{
    foreach ([
        ['key' => 'fast_preview', 'width' => 512, 'height' => 512],
        ['key' => 'gallery', 'width' => 1600, 'height' => 1600],
        ['key' => 'wall', 'width' => 1920, 'height' => 1920],
    ] as $variant) {
        EventMediaVariant::query()->create([
            'event_media_id' => $media->id,
            'variant_key' => $variant['key'],
            'disk' => 'public',
            'path' => "events/{$media->event_id}/variants/{$media->id}/{$variant['key']}.webp",
            'width' => $variant['width'],
            'height' => $variant['height'],
            'size_bytes' => 1024,
            'mime_type' => 'image/webp',
        ]);
    }
}

it('returns a single runtime asset for public puzzle payloads and prefers the wall variant on rich devices', function () {
    $event = Event::factory()->active()->create();

    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'play',
        'is_enabled' => true,
    ]);

    EventPlaySetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
        'puzzle_enabled' => true,
    ]);

    $gameType = PlayGameType::factory()->create([
        'key' => 'puzzle',
        'name' => 'Puzzle',
    ]);

    $game = PlayEventGame::factory()->create([
        'event_id' => $event->id,
        'game_type_id' => $gameType->id,
        'slug' => 'puzzle-publico',
        'settings_json' => [
            'gridSize' => '3x3',
        ],
    ]);

    $mediaItems = EventMedia::factory()->published()->count(3)->create([
        'event_id' => $event->id,
    ]);
    $mediaItems->each(fn (EventMedia $media) => attachPuzzleVariants($media));

    $manifestResponse = $this->apiGet("/public/events/{$event->slug}/play");

    $this->assertApiSuccess($manifestResponse);
    $manifestResponse->assertJsonPath('data.games.0.slug', 'puzzle-publico')
        ->assertJsonPath('data.games.0.readiness.published', true)
        ->assertJsonPath('data.games.0.readiness.launchable', true)
        ->assertJsonPath('data.games.0.readiness.bootable', true)
        ->assertJsonPath('data.games.0.readiness.reason', null);

    $showResponse = $this->apiGet("/public/events/{$event->slug}/play/{$game->slug}?platform=iPhone&viewport_width=430&viewport_height=932&pixel_ratio=3&save_data=0&effective_type=4g&downlink=10");

    $this->assertApiSuccess($showResponse);
    $showResponse->assertJsonPath('data.game.slug', 'puzzle-publico')
        ->assertJsonPath('data.game.readiness.published', true)
        ->assertJsonPath('data.game.readiness.launchable', true)
        ->assertJsonPath('data.game.readiness.bootable', true)
        ->assertJsonPath('data.game.readiness.reason', null)
        ->assertJsonCount(1, 'data.runtime.assets')
        ->assertJsonPath('data.runtime.assets.0.variantKey', 'wall')
        ->assertJsonPath('data.runtime.assets.0.deliveryProfile', 'rich');

    $startResponse = $this->apiPost("/public/events/{$event->slug}/play/{$game->slug}/sessions", [
        'playerIdentifier' => 'browserhash-puzzle',
        'displayName' => 'Anderson',
        'device' => [
            'platform' => 'ios',
            'viewportWidth' => 430,
            'viewportHeight' => 932,
            'pixelRatio' => 3,
            'connection' => [
                'saveData' => false,
                'effectiveType' => '4g',
                'downlink' => 10,
            ],
        ],
    ]);

    $this->assertApiSuccess($startResponse, 201);
    $startResponse->assertJsonPath('data.gameKey', 'puzzle')
        ->assertJsonCount(1, 'data.assets')
        ->assertJsonPath('data.assets.0.variantKey', 'wall')
        ->assertJsonPath('data.assets.0.deliveryProfile', 'rich');
});

it('filters out video runtime assets for puzzle and blocks session start without a valid image cover', function () {
    $event = Event::factory()->active()->create();

    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'play',
        'is_enabled' => true,
    ]);

    EventPlaySetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
        'puzzle_enabled' => true,
    ]);

    $gameType = PlayGameType::factory()->create([
        'key' => 'puzzle',
        'name' => 'Puzzle',
    ]);

    $game = PlayEventGame::factory()->create([
        'event_id' => $event->id,
        'game_type_id' => $gameType->id,
        'slug' => 'puzzle-video-only',
        'settings_json' => [
            'gridSize' => '3x3',
        ],
    ]);

    EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'original_filename' => 'videos/puzzle-source.mp4',
        'original_path' => "events/{$event->id}/originals/puzzle-source.mp4",
        'width' => 1080,
        'height' => 1920,
        'duration_seconds' => 12,
        'has_audio' => false,
        'container' => 'video/mp4',
    ]);

    $manifestResponse = $this->apiGet("/public/events/{$event->slug}/play");

    $this->assertApiSuccess($manifestResponse);
    $manifestResponse->assertJsonPath('data.games.0.slug', 'puzzle-video-only')
        ->assertJsonPath('data.games.0.readiness.published', true)
        ->assertJsonPath('data.games.0.readiness.launchable', false)
        ->assertJsonPath('data.games.0.readiness.bootable', false)
        ->assertJsonPath('data.games.0.readiness.reason', 'puzzle.no_image_available');

    $showResponse = $this->apiGet("/public/events/{$event->slug}/play/{$game->slug}");

    $this->assertApiSuccess($showResponse);
    $showResponse->assertJsonPath('data.game.slug', 'puzzle-video-only')
        ->assertJsonPath('data.game.readiness.published', true)
        ->assertJsonPath('data.game.readiness.launchable', false)
        ->assertJsonPath('data.game.readiness.bootable', false)
        ->assertJsonPath('data.game.readiness.reason', 'puzzle.no_image_available')
        ->assertJsonCount(0, 'data.runtime.assets');

    $startResponse = $this->apiPost("/public/events/{$event->slug}/play/{$game->slug}/sessions", [
        'playerIdentifier' => 'browserhash-puzzle-video-only',
    ]);

    $startResponse->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.reason', 'puzzle.no_image_available');
});
