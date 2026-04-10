<?php

use App\Modules\Events\Models\Event;
use App\Modules\Wall\Models\EventWallSetting;

it('allows existing non-puzzle layouts while puzzle rollout is disabled', function () {
    [$user, $organization] = $this->actingAsManager();

    config()->set('wall.layouts.puzzle.enabled', false);

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventWallSetting::factory()->create([
        'event_id' => $event->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}/wall/settings", [
        'layout' => 'cinematic',
        'theme_config' => null,
    ]);

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.settings.layout', 'cinematic')
        ->assertJsonPath('data.settings.theme_config', []);
});
