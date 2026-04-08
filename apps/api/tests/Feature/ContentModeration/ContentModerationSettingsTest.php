<?php

use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\ContentModeration\Models\ContentModerationGlobalSetting;
use App\Modules\Events\Models\Event;

it('returns default content moderation settings for an event when none were persisted yet', function () {
    [$user, $organization] = $this->actingAsOwner();

    ContentModerationGlobalSetting::query()->create([
        'id' => 1,
        'enabled' => true,
        'provider_key' => 'openai',
        'mode' => 'observe_only',
        'threshold_version' => 'global-policy-v1',
        'fallback_mode' => 'review',
        'analysis_scope' => 'image_only',
        'normalized_text_context_mode' => 'body_only',
        'hard_block_thresholds_json' => [
            'nudity' => 0.92,
            'violence' => 0.91,
            'self_harm' => 0.93,
        ],
        'review_thresholds_json' => [
            'nudity' => 0.61,
            'violence' => 0.62,
            'self_harm' => 0.63,
        ],
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiGet("/events/{$event->id}/content-moderation/settings");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.provider_key', 'openai')
        ->assertJsonPath('data.mode', 'observe_only')
        ->assertJsonPath('data.threshold_version', 'global-policy-v1')
        ->assertJsonPath('data.fallback_mode', 'review')
        ->assertJsonPath('data.analysis_scope', 'image_only')
        ->assertJsonPath('data.objective_safety_scope', 'image_only')
        ->assertJsonPath('data.normalized_text_context_mode', 'body_only')
        ->assertJsonPath('data.inherits_global', true);
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
        'analysis_scope' => 'image_only',
        'normalized_text_context_mode' => 'caption_only',
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
        ->assertJsonPath('data.analysis_scope', 'image_only')
        ->assertJsonPath('data.objective_safety_scope', 'image_only')
        ->assertJsonPath('data.normalized_text_context_mode', 'caption_only')
        ->assertJsonPath('data.inherits_global', false)
        ->assertJsonPath('data.hard_block_thresholds.nudity', 0.96)
        ->assertJsonPath('data.review_thresholds.self_harm', 0.66);

    $this->assertDatabaseHas('event_content_moderation_settings', [
        'event_id' => $event->id,
        'enabled' => true,
        'provider_key' => 'openai',
        'threshold_version' => 'wedding-v1',
        'fallback_mode' => 'review',
        'analysis_scope' => 'image_only',
        'normalized_text_context_mode' => 'caption_only',
    ]);
});

it('resets content moderation settings to inherit the global policy for an event', function () {
    [$user, $organization] = $this->actingAsOwner();

    ContentModerationGlobalSetting::query()->create([
        'id' => 1,
        'enabled' => true,
        'provider_key' => 'openai',
        'mode' => 'observe_only',
        'threshold_version' => 'global-policy-v2',
        'fallback_mode' => 'review',
        'analysis_scope' => 'image_and_text_context',
        'normalized_text_context_mode' => 'body_plus_caption',
        'hard_block_thresholds_json' => [
            'nudity' => 0.91,
            'violence' => 0.92,
            'self_harm' => 0.93,
        ],
        'review_thresholds_json' => [
            'nudity' => 0.61,
            'violence' => 0.62,
            'self_harm' => 0.63,
        ],
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    EventContentModerationSetting::factory()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'provider_key' => 'noop',
        'mode' => 'enforced',
        'threshold_version' => 'custom-policy',
        'fallback_mode' => 'block',
    ]);

    $response = $this->apiPatch("/events/{$event->id}/content-moderation/settings", [
        'inherit_global' => true,
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.provider_key', 'openai')
        ->assertJsonPath('data.mode', 'observe_only')
        ->assertJsonPath('data.threshold_version', 'global-policy-v2')
        ->assertJsonPath('data.analysis_scope', 'image_and_text_context')
        ->assertJsonPath('data.objective_safety_scope', 'image_and_text_context')
        ->assertJsonPath('data.normalized_text_context_mode', 'body_plus_caption')
        ->assertJsonPath('data.inherits_global', true);

    $this->assertDatabaseMissing('event_content_moderation_settings', [
        'event_id' => $event->id,
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
