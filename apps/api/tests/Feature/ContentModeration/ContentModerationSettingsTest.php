<?php

use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\Events\Models\Event;

it('returns default content moderation settings for an event when none were persisted yet', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiGet("/events/{$event->id}/content-moderation/settings");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.provider_key', 'openai')
        ->assertJsonPath('data.fallback_mode', 'review');
});

it('updates content moderation settings for an event', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}/content-moderation/settings", [
        'enabled' => true,
        'provider_key' => 'openai',
        'mode' => 'enforced',
        'threshold_version' => 'wedding-v1',
        'fallback_mode' => 'review',
        'hard_block_thresholds' => [
            'nudity' => 0.96,
            'violence' => 0.93,
            'self_harm' => 0.95,
        ],
        'review_thresholds' => [
            'nudity' => 0.61,
            'violence' => 0.64,
            'self_harm' => 0.66,
        ],
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.provider_key', 'openai')
        ->assertJsonPath('data.hard_block_thresholds.nudity', 0.96)
        ->assertJsonPath('data.review_thresholds.self_harm', 0.66);

    $this->assertDatabaseHas('event_content_moderation_settings', [
        'event_id' => $event->id,
        'enabled' => true,
        'provider_key' => 'openai',
        'threshold_version' => 'wedding-v1',
        'fallback_mode' => 'review',
    ]);
});

it('forbids updating content moderation settings without permission in the event organization', function () {
    [$user, $organization] = $this->actingAsViewer();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}/content-moderation/settings", [
        'enabled' => true,
        'provider_key' => 'openai',
        'fallback_mode' => 'review',
        'hard_block_thresholds' => [
            'nudity' => 0.9,
            'violence' => 0.9,
            'self_harm' => 0.9,
        ],
        'review_thresholds' => [
            'nudity' => 0.6,
            'violence' => 0.6,
            'self_harm' => 0.6,
        ],
    ]);

    $this->assertApiForbidden($response);

    expect(EventContentModerationSetting::query()->count())->toBe(0);
});
