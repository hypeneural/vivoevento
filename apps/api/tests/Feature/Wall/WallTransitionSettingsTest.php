<?php

use App\Modules\Events\Models\Event;
use App\Modules\Wall\Models\EventWallSetting;

it('stores and returns transition_mode in wall settings responses', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventWallSetting::factory()->create([
        'event_id' => $event->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}/wall/settings", [
        'transition_effect' => 'slide',
        'transition_mode' => 'random',
    ]);

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.settings.transition_effect', 'slide')
        ->assertJsonPath('data.settings.transition_mode', 'random');

    $this->assertDatabaseHas('event_wall_settings', [
        'event_id' => $event->id,
        'transition_effect' => 'slide',
        'transition_mode' => 'random',
    ]);

    $showResponse = $this->apiGet("/events/{$event->id}/wall/settings");

    $this->assertApiSuccess($showResponse);
    $showResponse->assertJsonPath('data.settings.transition_mode', 'random');
});

it('stores and returns a sanitized transition_pool in wall settings responses', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventWallSetting::factory()->create([
        'event_id' => $event->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}/wall/settings", [
        'transition_effect' => 'slide',
        'transition_mode' => 'random',
        'transition_pool' => ['slide', 'lift-fade', 'cross-zoom'],
    ]);

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.settings.transition_mode', 'random')
        ->assertJsonPath('data.settings.transition_pool', ['slide', 'lift-fade', 'cross-zoom']);

    $stored = EventWallSetting::query()
        ->where('event_id', $event->id)
        ->firstOrFail();

    expect($stored->transition_pool)->toBe(['slide', 'lift-fade', 'cross-zoom']);

    $showResponse = $this->apiGet("/events/{$event->id}/wall/settings");

    $this->assertApiSuccess($showResponse);
    $showResponse->assertJsonPath('data.settings.transition_pool', ['slide', 'lift-fade', 'cross-zoom']);
});

it('defaults transition_mode to fixed when wall settings are updated without the new field', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventWallSetting::factory()->create([
        'event_id' => $event->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}/wall/settings", [
        'layout' => 'cinematic',
    ]);

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.settings.layout', 'cinematic')
        ->assertJsonPath('data.settings.transition_mode', 'fixed');

    $this->assertDatabaseHas('event_wall_settings', [
        'event_id' => $event->id,
        'transition_mode' => 'fixed',
    ]);
});

it('accepts transition_mode in wall simulation drafts', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventWallSetting::factory()->create([
        'event_id' => $event->id,
    ]);

    $response = $this->apiPost("/events/{$event->id}/wall/simulate", [
        'transition_effect' => 'zoom',
        'transition_mode' => 'random',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonStructure([
        'success',
        'data' => [
            'summary',
            'sequence_preview',
            'explanation',
        ],
    ]);
});

it('accepts transition_pool in wall simulation drafts', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventWallSetting::factory()->create([
        'event_id' => $event->id,
    ]);

    $response = $this->apiPost("/events/{$event->id}/wall/simulate", [
        'transition_effect' => 'zoom',
        'transition_mode' => 'random',
        'transition_pool' => ['fade', 'swipe-up'],
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonStructure([
        'success',
        'data' => [
            'summary',
            'sequence_preview',
            'explanation',
        ],
    ]);
});

it('rejects invalid transition_mode values for update and simulate', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventWallSetting::factory()->create([
        'event_id' => $event->id,
    ]);

    $updateResponse = $this->apiPatch("/events/{$event->id}/wall/settings", [
        'transition_mode' => 'shuffle',
    ]);

    $updateResponse->assertStatus(422)
        ->assertJsonValidationErrors(['transition_mode']);

    $simulateResponse = $this->apiPost("/events/{$event->id}/wall/simulate", [
        'transition_mode' => 'shuffle',
    ]);

    $simulateResponse->assertStatus(422)
        ->assertJsonValidationErrors(['transition_mode']);
});

it('rejects invalid transition_pool values for update and simulate', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventWallSetting::factory()->create([
        'event_id' => $event->id,
    ]);

    $updateResponse = $this->apiPatch("/events/{$event->id}/wall/settings", [
        'transition_mode' => 'random',
        'transition_pool' => ['fade', 'none'],
    ]);

    $updateResponse->assertStatus(422)
        ->assertJsonValidationErrors(['transition_pool.1']);

    $simulateResponse = $this->apiPost("/events/{$event->id}/wall/simulate", [
        'transition_mode' => 'random',
        'transition_pool' => ['fade', 'none'],
    ]);

    $simulateResponse->assertStatus(422)
        ->assertJsonValidationErrors(['transition_pool.1']);
});

it('rejects duplicate transition_pool values for update and simulate', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventWallSetting::factory()->create([
        'event_id' => $event->id,
    ]);

    $updateResponse = $this->apiPatch("/events/{$event->id}/wall/settings", [
        'transition_mode' => 'random',
        'transition_pool' => ['fade', 'fade'],
    ]);

    $updateResponse->assertStatus(422)
        ->assertJsonValidationErrors(['transition_pool.1']);

    $simulateResponse = $this->apiPost("/events/{$event->id}/wall/simulate", [
        'transition_mode' => 'random',
        'transition_pool' => ['fade', 'fade'],
    ]);

    $simulateResponse->assertStatus(422)
        ->assertJsonValidationErrors(['transition_pool.1']);
});
