<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\Play\Models\EventPlaySetting;
use App\Modules\Play\Models\PlayEventGame;

it('creates and lists play games for an event manager payload', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'play',
        'is_enabled' => true,
    ]);

    EventPlaySetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
    ]);

    $storeResponse = $this->apiPost("/events/{$event->id}/play/games", [
        'game_type_key' => 'memory',
        'title' => 'Memoria do Evento',
        'slug' => 'memoria-evento',
        'ranking_enabled' => true,
        'settings' => [
            'pairsCount' => 8,
            'difficulty' => 'normal',
            'showPreviewSeconds' => 3,
        ],
    ]);

    $this->assertApiSuccess($storeResponse, 201);
    $storeResponse->assertJsonPath('data.game_type_key', 'memory')
        ->assertJsonPath('data.slug', 'memoria-evento')
        ->assertJsonPath('data.settings.difficulty', 'normal');

    $managerResponse = $this->apiGet("/events/{$event->id}/play");

    $this->assertApiSuccess($managerResponse);
    $managerResponse->assertJsonPath('data.settings.is_enabled', true)
        ->assertJsonPath('data.games.0.slug', 'memoria-evento');
});

it('updates play game assets for an event', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'play',
        'is_enabled' => true,
    ]);

    $game = PlayEventGame::factory()->create([
        'event_id' => $event->id,
    ]);

    $media = \App\Modules\MediaProcessing\Models\EventMedia::factory()->published()->create([
        'event_id' => $event->id,
    ]);

    $response = $this->apiPost("/events/{$event->id}/play/games/{$game->id}/assets", [
        'assets' => [
            [
                'media_id' => $media->id,
                'role' => 'primary',
                'sort_order' => 1,
            ],
        ],
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.assets.0.media_id', $media->id);
});

it('rejects unsupported puzzle settings for the public launch baseline', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'play',
        'is_enabled' => true,
    ]);

    EventPlaySetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
    ]);

    $response = $this->apiPost("/events/{$event->id}/play/games", [
        'game_type_key' => 'puzzle',
        'title' => 'Puzzle Avancado',
        'settings' => [
            'gridSize' => '4x4',
            'showReferenceImage' => true,
            'snapEnabled' => true,
            'dragTolerance' => 18,
        ],
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.gridSize'))->not->toBeNull();
});
