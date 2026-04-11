<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use App\Modules\Play\Events\PlayLeaderboardUpdated;
use App\Modules\Play\Models\EventPlaySetting;
use App\Modules\Play\Models\PlayEventGame;
use App\Modules\Play\Models\PlayGameType;
use Illuminate\Support\Facades\Event as EventFacade;

function attachPlayVariants(EventMedia $media): void
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

it('starts and finishes a public play session and exposes ranking data', function () {
    EventFacade::fake([PlayLeaderboardUpdated::class]);

    $event = Event::factory()->active()->create();

    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'play',
        'is_enabled' => true,
    ]);

    EventPlaySetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
        'ranking_enabled' => true,
    ]);

    $gameType = PlayGameType::factory()->create([
        'key' => 'memory',
        'name' => 'Jogo da Memoria',
    ]);

    $game = PlayEventGame::factory()->create([
        'event_id' => $event->id,
        'game_type_id' => $gameType->id,
        'slug' => 'memoria-publica',
        'settings_json' => [
            'pairsCount' => 6,
            'difficulty' => 'normal',
        ],
    ]);

    $mediaItems = \App\Modules\MediaProcessing\Models\EventMedia::factory()->published()->count(6)->create([
        'event_id' => $event->id,
    ]);
    $mediaItems->each(fn (EventMedia $media) => attachPlayVariants($media));

    $manifestResponse = $this->apiGet("/public/events/{$event->slug}/play");
    $this->assertApiSuccess($manifestResponse);
    $manifestResponse->assertJsonPath('data.games.0.slug', 'memoria-publica')
        ->assertJsonPath('data.games.0.readiness.published', true)
        ->assertJsonPath('data.games.0.readiness.launchable', true)
        ->assertJsonPath('data.games.0.readiness.bootable', true)
        ->assertJsonPath('data.games.0.readiness.reason', null)
        ->assertJsonPath('data.pwa.installable', true);

    $startResponse = $this->apiPost("/public/events/{$event->slug}/play/{$game->slug}/sessions", [
        'playerIdentifier' => 'browserhash-abc123',
        'displayName' => 'Anderson',
        'device' => [
            'platform' => 'ios',
            'viewportWidth' => 390,
            'viewportHeight' => 844,
            'pixelRatio' => 3,
            'connection' => [
                'saveData' => true,
                'effectiveType' => '3g',
                'downlink' => 1.1,
            ],
        ],
    ]);

    $this->assertApiSuccess($startResponse, 201);
    $sessionUuid = $startResponse->json('data.sessionUuid');
    $resumeToken = $startResponse->json('data.resumeToken');

    expect($startResponse->json('data.gameKey'))->toBe('memory')
        ->and($resumeToken)->not->toBeEmpty()
        ->and($startResponse->json('data.assets'))->toHaveCount(6)
        ->and($startResponse->json('data.assets.0.variantKey'))->toBe('fast_preview')
        ->and($startResponse->json('data.assets.0.deliveryProfile'))->toBe('constrained')
        ->and($startResponse->json('data.analytics.total_moves'))->toBe(0);

    $movesResponse = $this->apiPost("/public/play/sessions/{$sessionUuid}/moves", [
        'moves' => [
            [
                'move_number' => 1,
                'move_type' => 'flip',
                'payload' => ['assetId' => 101],
            ],
            [
                'move_number' => 2,
                'move_type' => 'flip',
                'payload' => ['assetId' => 102],
            ],
            [
                'move_number' => 3,
                'move_type' => 'mismatch',
                'payload' => ['firstAssetId' => 101, 'secondAssetId' => 102],
            ],
        ],
    ]);

    $this->assertApiSuccess($movesResponse);
    $movesResponse->assertJsonPath('data.accepted_moves', 3)
        ->assertJsonPath('data.analytics.total_moves', 3)
        ->assertJsonPath('data.analytics.move_type_breakdown.flip', 2)
        ->assertJsonPath('data.analytics.move_type_breakdown.mismatch', 1);

    $sessionAnalyticsResponse = $this->apiGet("/public/play/sessions/{$sessionUuid}/analytics");

    $this->assertApiSuccess($sessionAnalyticsResponse);
    $sessionAnalyticsResponse->assertJsonPath('data.analytics.total_moves', 3)
        ->assertJsonPath('data.session.uuid', $sessionUuid);

    $finishResponse = $this->apiPost("/public/play/sessions/{$sessionUuid}/finish", [
        'clientResult' => [
            'score' => 920,
            'completed' => true,
            'timeMs' => 48200,
            'moves' => 18,
            'mistakes' => 4,
            'accuracy' => 0.78,
            'metadata' => [
                'pairsCount' => 6,
                'difficulty' => 'normal',
            ],
        ],
    ]);

    $this->assertApiSuccess($finishResponse);
    $finishResponse->assertJsonPath('data.status', 'finished')
        ->assertJsonPath('data.result.score', 774)
        ->assertJsonPath('data.authoritative_result.score', 774)
        ->assertJsonPath('data.result.authoritative', true)
        ->assertJsonPath('data.analytics.total_moves', 3)
        ->assertJsonPath('data.game_analytics.total_sessions', 1)
        ->assertJsonPath('data.game_analytics.total_moves', 3)
        ->assertJsonPath('data.leaderboard.0.position', 1);

    EventFacade::assertDispatched(PlayLeaderboardUpdated::class, function (PlayLeaderboardUpdated $event) use ($game) {
        return $event->gameUuid === $game->uuid
            && ($event->payload['leaderboard'][0]['best_score'] ?? null) === 774
            && ($event->payload['analytics']['total_sessions'] ?? null) === 1;
    });

    $rankingResponse = $this->apiGet("/public/events/{$event->slug}/play/{$game->slug}/ranking");

    $this->assertApiSuccess($rankingResponse);
    $rankingResponse->assertJsonPath('data.0.position', 1)
        ->assertJsonPath('data.0.player_name', 'Anderson')
        ->assertJsonPath('data.0.best_score', 774);

    $lastPlaysResponse = $this->apiGet("/public/events/{$event->slug}/play/{$game->slug}/last-plays");

    $this->assertApiSuccess($lastPlaysResponse);
    $lastPlaysResponse->assertJsonPath('data.0.player_name', 'Anderson');

    $showResponse = $this->apiGet("/public/events/{$event->slug}/play/{$game->slug}?save_data=1&effective_type=3g&downlink=1.1&viewport_width=390&viewport_height=844&pixel_ratio=3");

    $this->assertApiSuccess($showResponse);
    $showResponse->assertJsonPath('data.game.readiness.published', true)
        ->assertJsonPath('data.game.readiness.launchable', true)
        ->assertJsonPath('data.game.readiness.bootable', true)
        ->assertJsonPath('data.game.readiness.reason', null)
        ->assertJsonPath('data.runtime.analytics.total_sessions', 1)
        ->assertJsonPath('data.runtime.analytics.total_moves', 3)
        ->assertJsonPath('data.runtime.assets.0.variantKey', 'fast_preview')
        ->assertJsonPath('data.runtime.assets.0.deliveryProfile', 'constrained')
        ->assertJsonPath('data.runtime.realtime.channel', "play.game.{$game->uuid}")
        ->assertJsonPath('data.runtime.realtime.events.leaderboard_updated', 'play.leaderboard.updated');
});

it('supports heartbeat and resume for an active session within the resume window', function () {
    $event = Event::factory()->active()->create();

    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'play',
        'is_enabled' => true,
    ]);

    EventPlaySetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
        'ranking_enabled' => true,
    ]);

    $gameType = PlayGameType::factory()->create([
        'key' => 'memory',
        'name' => 'Jogo da Memoria',
    ]);

    $game = PlayEventGame::factory()->create([
        'event_id' => $event->id,
        'game_type_id' => $gameType->id,
        'slug' => 'memoria-resume',
        'settings_json' => [
            'pairsCount' => 6,
            'difficulty' => 'normal',
        ],
    ]);

    \App\Modules\MediaProcessing\Models\EventMedia::factory()->published()->count(6)->create([
        'event_id' => $event->id,
    ]);

    $startResponse = $this->apiPost("/public/events/{$event->slug}/play/{$game->slug}/sessions", [
        'playerIdentifier' => 'browserhash-resume',
        'displayName' => 'Marina',
    ]);

    $this->assertApiSuccess($startResponse, 201);
    $sessionUuid = $startResponse->json('data.sessionUuid');
    $resumeToken = $startResponse->json('data.resumeToken');

    $movesResponse = $this->apiPost("/public/play/sessions/{$sessionUuid}/moves", [
        'batchNumber' => 1,
        'moves' => [
            [
                'moveNumber' => 1,
                'type' => 'flip',
                'payload' => ['assetId' => 101],
            ],
            [
                'moveNumber' => 2,
                'type' => 'match',
                'payload' => ['assetId' => 101],
            ],
        ],
    ]);

    $this->assertApiSuccess($movesResponse);
    $movesResponse->assertJsonPath('data.accepted_moves', 2);

    $heartbeatResponse = $this->apiPost("/public/play/sessions/{$sessionUuid}/heartbeat", [
        'state' => 'hidden',
        'reason' => 'visibilitychange',
        'elapsedMs' => 5000,
    ]);

    $this->assertApiSuccess($heartbeatResponse);
    $heartbeatResponse->assertJsonPath('data.session.status', 'paused');

    $this->travel(121)->seconds();

    $analyticsResponse = $this->apiGet("/public/play/sessions/{$sessionUuid}/analytics");
    $this->assertApiSuccess($analyticsResponse);
    $analyticsResponse->assertJsonPath('data.session.status', 'abandoned');

    $resumeResponse = $this->apiPost("/public/play/sessions/{$sessionUuid}/resume", [
        'resumeToken' => $resumeToken,
    ]);

    $this->assertApiSuccess($resumeResponse);
    $resumeResponse->assertJsonPath('data.status', 'started')
        ->assertJsonPath('data.restore.lastAcceptedMoveNumber', 2)
        ->assertJsonPath('data.restore.moves.0.type', 'flip')
        ->assertJsonPath('data.restore.moves.1.type', 'match');
});

it('rejects resume after the resume window expires', function () {
    $event = Event::factory()->active()->create();

    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'play',
        'is_enabled' => true,
    ]);

    EventPlaySetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
    ]);

    $gameType = PlayGameType::factory()->create([
        'key' => 'memory',
        'name' => 'Jogo da Memoria',
    ]);

    $game = PlayEventGame::factory()->create([
        'event_id' => $event->id,
        'game_type_id' => $gameType->id,
        'slug' => 'memoria-expirada',
        'settings_json' => [
            'pairsCount' => 6,
            'difficulty' => 'normal',
        ],
    ]);

    \App\Modules\MediaProcessing\Models\EventMedia::factory()->published()->count(6)->create([
        'event_id' => $event->id,
    ]);

    $startResponse = $this->apiPost("/public/events/{$event->slug}/play/{$game->slug}/sessions", [
        'playerIdentifier' => 'browserhash-expired',
    ]);

    $this->assertApiSuccess($startResponse, 201);
    $sessionUuid = $startResponse->json('data.sessionUuid');
    $resumeToken = $startResponse->json('data.resumeToken');

    $this->travel(181)->seconds();

    $resumeResponse = $this->apiPost("/public/play/sessions/{$sessionUuid}/resume", [
        'resumeToken' => $resumeToken,
    ]);

    $resumeResponse->assertStatus(410)
        ->assertJsonPath('success', false);
});

it('accepts browser boolean query strings when loading a public play game', function () {
    $event = Event::factory()->active()->create();

    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'play',
        'is_enabled' => true,
    ]);

    EventPlaySetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
        'ranking_enabled' => true,
    ]);

    $gameType = PlayGameType::factory()->create([
        'key' => 'memory',
        'name' => 'Jogo da Memoria',
    ]);

    $game = PlayEventGame::factory()->create([
        'event_id' => $event->id,
        'game_type_id' => $gameType->id,
        'slug' => 'memoria-browser-params',
        'settings_json' => [
            'pairsCount' => 6,
            'difficulty' => 'normal',
        ],
    ]);

    $mediaItems = EventMedia::factory()->published()->count(6)->create([
        'event_id' => $event->id,
    ]);
    $mediaItems->each(fn (EventMedia $media) => attachPlayVariants($media));

    $falseResponse = $this->apiGet("/public/events/{$event->slug}/play/{$game->slug}?platform=Win32&viewport_width=796&viewport_height=953&pixel_ratio=1&save_data=false&effective_type=4g&downlink=10");

    $this->assertApiSuccess($falseResponse);
    $falseResponse->assertJsonPath('data.game.slug', 'memoria-browser-params')
        ->assertJsonPath('data.runtime.assets.0.deliveryProfile', 'rich');

    $trueResponse = $this->apiGet("/public/events/{$event->slug}/play/{$game->slug}?platform=Win32&viewportWidth=796&viewportHeight=953&pixelRatio=1&saveData=true&effectiveType=4g&downlink=10");

    $this->assertApiSuccess($trueResponse);
    $trueResponse->assertJsonPath('data.runtime.assets.0.variantKey', 'fast_preview')
        ->assertJsonPath('data.runtime.assets.0.deliveryProfile', 'constrained');
});
