<?php

use App\Modules\MediaIntelligence\Models\MediaIntelligenceGlobalSetting;

it('returns default media intelligence global settings for super-admin when none were persisted yet', function () {
    [$user] = $this->actingAsSuperAdmin();

    $response = $this->apiGet('/media-intelligence/global-settings');

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.reply_text_prompt', MediaIntelligenceGlobalSetting::defaultReplyTextPrompt())
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.provider_key', 'vllm')
        ->assertJsonPath('data.mode', 'enrich_only')
        ->assertJsonPath('data.contextual_policy_preset_key', 'homologacao_livre')
        ->assertJsonPath('data.allow_alcohol', true)
        ->assertJsonPath('data.allow_tobacco', true)
        ->assertJsonPath('data.required_people_context', 'optional')
        ->assertJsonPath('data.blocked_terms', [])
        ->assertJsonPath('data.allowed_exceptions', [])
        ->assertJsonPath('data.freeform_instruction', null)
        ->assertJsonPath('data.reply_text_fixed_templates', [])
        ->assertJsonPath('data.reply_ai_rate_limit_enabled', false)
        ->assertJsonPath('data.reply_ai_rate_limit_max_messages', 10)
        ->assertJsonPath('data.reply_ai_rate_limit_window_minutes', 10);
});

it('updates media intelligence global settings for super-admin', function () {
    [$user] = $this->actingAsSuperAdmin();

    $response = $this->apiPatch('/media-intelligence/global-settings', [
        'enabled' => true,
        'provider_key' => 'openrouter',
        'model_key' => 'openai/gpt-4.1-mini',
        'mode' => 'gate',
        'context_scope' => 'image_only',
        'reply_scope' => 'image_and_text_context',
        'normalized_text_context_mode' => 'caption_only',
        'response_schema_version' => 'contextual-v2',
        'contextual_policy_preset_key' => 'corporativo_restrito',
        'allow_alcohol' => false,
        'allow_tobacco' => false,
        'required_people_context' => 'required',
        'blocked_terms' => [
            'copos',
            'charutos',
        ],
        'allowed_exceptions' => [
            'palestrante no palco',
        ],
        'freeform_instruction' => 'Prefira review quando a imagem mostrar somente objetos.',
        'reply_text_prompt' => 'Sempre gere uma frase curtinha com emoji coerente com a foto.',
        'reply_text_fixed_templates' => [
            'Memorias que fazem o coracao sorrir! ðŸŽ‰ðŸ“¸',
            'Momento de risadas e lembrancas! ðŸ“±ðŸŽ‰',
        ],
        'reply_ai_rate_limit_enabled' => true,
        'reply_ai_rate_limit_max_messages' => 10,
        'reply_ai_rate_limit_window_minutes' => 15,
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.provider_key', 'openrouter')
        ->assertJsonPath('data.model_key', 'openai/gpt-4.1-mini')
        ->assertJsonPath('data.mode', 'gate')
        ->assertJsonPath('data.context_scope', 'image_only')
        ->assertJsonPath('data.reply_scope', 'image_and_text_context')
        ->assertJsonPath('data.normalized_text_context_mode', 'caption_only')
        ->assertJsonPath('data.response_schema_version', 'contextual-v2')
        ->assertJsonPath('data.contextual_policy_preset_key', 'corporativo_restrito')
        ->assertJsonPath('data.allow_alcohol', false)
        ->assertJsonPath('data.allow_tobacco', false)
        ->assertJsonPath('data.required_people_context', 'required')
        ->assertJsonPath('data.blocked_terms.0', 'copos')
        ->assertJsonPath('data.allowed_exceptions.0', 'palestrante no palco')
        ->assertJsonPath('data.freeform_instruction', 'Prefira review quando a imagem mostrar somente objetos.')
        ->assertJsonPath('data.reply_text_prompt', 'Sempre gere uma frase curtinha com emoji coerente com a foto.')
        ->assertJsonPath('data.reply_text_fixed_templates.0', 'Memorias que fazem o coracao sorrir! ðŸŽ‰ðŸ“¸')
        ->assertJsonPath('data.reply_text_fixed_templates.1', 'Momento de risadas e lembrancas! ðŸ“±ðŸŽ‰')
        ->assertJsonPath('data.reply_ai_rate_limit_enabled', true)
        ->assertJsonPath('data.reply_ai_rate_limit_max_messages', 10)
        ->assertJsonPath('data.reply_ai_rate_limit_window_minutes', 15);

    $this->assertDatabaseHas('media_intelligence_global_settings', [
        'id' => 1,
        'enabled' => true,
        'provider_key' => 'openrouter',
        'model_key' => 'openai/gpt-4.1-mini',
        'mode' => 'gate',
        'context_scope' => 'image_only',
        'reply_scope' => 'image_and_text_context',
        'normalized_text_context_mode' => 'caption_only',
        'response_schema_version' => 'contextual-v2',
        'contextual_policy_preset_key' => 'corporativo_restrito',
        'allow_alcohol' => false,
        'allow_tobacco' => false,
        'required_people_context' => 'required',
        'freeform_instruction' => 'Prefira review quando a imagem mostrar somente objetos.',
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
