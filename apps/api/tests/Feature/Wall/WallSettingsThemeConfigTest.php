<?php

use App\Modules\Events\Models\Event;
use App\Modules\Wall\Models\EventWallSetting;

it('stores and returns wall theme_config for puzzle settings without requiring a rollout gate', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventWallSetting::factory()->create([
        'event_id' => $event->id,
    ]);

    $payload = [
        'layout' => 'puzzle',
        'theme_config' => [
            'preset' => 'standard',
            'anchor_mode' => 'event_brand',
            'burst_intensity' => 'normal',
            'hero_enabled' => true,
            'video_behavior' => 'fallback_single_item',
        ],
    ];

    $response = $this->apiPatch("/events/{$event->id}/wall/settings", $payload);

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.settings.layout', 'puzzle')
        ->assertJsonPath('data.settings.theme_config.preset', 'standard')
        ->assertJsonPath('data.settings.theme_config.video_behavior', 'fallback_single_item');

    $this->assertDatabaseHas('event_wall_settings', [
        'event_id' => $event->id,
        'layout' => 'puzzle',
    ]);

    expect(EventWallSetting::query()->where('event_id', $event->id)->firstOrFail()->theme_config)->toMatchArray(
        $payload['theme_config'],
    );
});

it('still accepts puzzle layout when the legacy rollout config is disabled', function () {
    [$user, $organization] = $this->actingAsManager();

    config()->set('wall.layouts.puzzle.enabled', false);

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventWallSetting::factory()->create([
        'event_id' => $event->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}/wall/settings", [
        'layout' => 'puzzle',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.settings.layout', 'puzzle');
});

it('rejects invalid wall theme_config values', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventWallSetting::factory()->create([
        'event_id' => $event->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}/wall/settings", [
        'theme_config' => [
            'preset' => 'immersive',
            'video_behavior' => 'experimental_multi_playback',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors([
        'theme_config.preset',
        'theme_config.video_behavior',
    ]);
});
