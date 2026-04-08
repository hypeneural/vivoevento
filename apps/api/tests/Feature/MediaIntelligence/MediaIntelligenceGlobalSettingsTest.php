<?php

use App\Modules\MediaIntelligence\Models\MediaIntelligenceGlobalSetting;

it('returns default media intelligence global settings for super-admin when none were persisted yet', function () {
    [$user] = $this->actingAsSuperAdmin();

    $response = $this->apiGet('/media-intelligence/global-settings');

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.reply_text_prompt', MediaIntelligenceGlobalSetting::defaultReplyTextPrompt())
        ->assertJsonPath('data.reply_text_fixed_templates', [])
        ->assertJsonPath('data.reply_ai_rate_limit_enabled', false)
        ->assertJsonPath('data.reply_ai_rate_limit_max_messages', 10)
        ->assertJsonPath('data.reply_ai_rate_limit_window_minutes', 10);
});

it('updates media intelligence global settings for super-admin', function () {
    [$user] = $this->actingAsSuperAdmin();

    $response = $this->apiPatch('/media-intelligence/global-settings', [
        'reply_text_prompt' => 'Sempre gere uma frase curtinha com emoji coerente com a foto.',
        'reply_text_fixed_templates' => [
            'Memorias que fazem o coracao sorrir! 🎉📸',
            'Momento de risadas e lembrancas! 📱🎉',
        ],
        'reply_ai_rate_limit_enabled' => true,
        'reply_ai_rate_limit_max_messages' => 10,
        'reply_ai_rate_limit_window_minutes' => 15,
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.reply_text_prompt', 'Sempre gere uma frase curtinha com emoji coerente com a foto.')
        ->assertJsonPath('data.reply_text_fixed_templates.0', 'Memorias que fazem o coracao sorrir! 🎉📸')
        ->assertJsonPath('data.reply_text_fixed_templates.1', 'Momento de risadas e lembrancas! 📱🎉')
        ->assertJsonPath('data.reply_ai_rate_limit_enabled', true)
        ->assertJsonPath('data.reply_ai_rate_limit_max_messages', 10)
        ->assertJsonPath('data.reply_ai_rate_limit_window_minutes', 15);

    $this->assertDatabaseHas('media_intelligence_global_settings', [
        'id' => 1,
        'reply_text_prompt' => 'Sempre gere uma frase curtinha com emoji coerente com a foto.',
        'reply_ai_rate_limit_enabled' => true,
        'reply_ai_rate_limit_max_messages' => 10,
        'reply_ai_rate_limit_window_minutes' => 15,
    ]);
});

it('forbids organization owners from updating media intelligence global settings', function () {
    [$user] = $this->actingAsOwner();

    $response = $this->apiPatch('/media-intelligence/global-settings', [
        'reply_text_prompt' => 'Teste',
    ]);

    $this->assertApiForbidden($response);
});
