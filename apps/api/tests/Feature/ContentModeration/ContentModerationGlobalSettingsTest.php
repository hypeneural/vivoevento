<?php

use App\Modules\ContentModeration\Models\ContentModerationGlobalSetting;

it('returns default content moderation global settings for super-admin when none were persisted yet', function () {
    [$user] = $this->actingAsSuperAdmin();

    $response = $this->apiGet('/content-moderation/global-settings');

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.provider_key', 'openai')
        ->assertJsonPath('data.fallback_mode', 'review')
        ->assertJsonPath('data.analysis_scope', 'image_and_text_context')
        ->assertJsonPath('data.objective_safety_scope', 'image_and_text_context')
        ->assertJsonPath('data.normalized_text_context_mode', 'body_plus_caption');
});

it('updates content moderation global settings for super-admin', function () {
    [$user] = $this->actingAsSuperAdmin();

    $response = $this->apiPatch('/content-moderation/global-settings', [
        'enabled' => true,
        'provider_key' => 'openai',
        'mode' => 'observe_only',
        'threshold_version' => 'global-wedding-v1',
        'fallback_mode' => 'review',
        'analysis_scope' => 'image_only',
        'normalized_text_context_mode' => 'caption_only',
        'hard_block_thresholds' => [
            'nudity' => 0.97,
            'violence' => 0.95,
            'self_harm' => 0.94,
        ],
        'review_thresholds' => [
            'nudity' => 0.62,
            'violence' => 0.63,
            'self_harm' => 0.64,
        ],
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.mode', 'observe_only')
        ->assertJsonPath('data.threshold_version', 'global-wedding-v1')
        ->assertJsonPath('data.analysis_scope', 'image_only')
        ->assertJsonPath('data.objective_safety_scope', 'image_only')
        ->assertJsonPath('data.normalized_text_context_mode', 'caption_only')
        ->assertJsonPath('data.hard_block_thresholds.nudity', 0.97)
        ->assertJsonPath('data.review_thresholds.self_harm', 0.64);

    $this->assertDatabaseHas('content_moderation_global_settings', [
        'id' => 1,
        'enabled' => true,
        'provider_key' => 'openai',
        'mode' => 'observe_only',
        'threshold_version' => 'global-wedding-v1',
        'fallback_mode' => 'review',
        'analysis_scope' => 'image_only',
        'normalized_text_context_mode' => 'caption_only',
    ]);
});

it('forbids organization owners from updating content moderation global settings', function () {
    [$user] = $this->actingAsOwner();

    $response = $this->apiPatch('/content-moderation/global-settings', [
        'enabled' => true,
    ]);

    $this->assertApiForbidden($response);

    expect(ContentModerationGlobalSetting::query()->count())->toBe(0);
});
