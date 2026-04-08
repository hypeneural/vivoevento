<?php

use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Actions\UpsertEventMediaIntelligenceSettingsAction;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;

it('upserts media intelligence settings for an event', function () {
    $event = Event::factory()->create();

    $settings = app(UpsertEventMediaIntelligenceSettingsAction::class)->execute($event, [
        'enabled' => true,
        'provider_key' => 'vllm',
        'model_key' => 'Qwen/Qwen2.5-VL-7B-Instruct',
        'mode' => 'gate',
        'prompt_version' => 'wedding-v2',
        'approval_prompt' => 'Avalie contexto do casamento e retorne JSON.',
        'caption_style_prompt' => 'Legenda curta e elegante.',
        'response_schema_version' => 'foundation-v1',
        'timeout_ms' => 9000,
        'fallback_mode' => 'review',
        'require_json_output' => true,
        'context_scope' => 'image_only',
        'reply_scope' => 'image_and_text_context',
        'normalized_text_context_mode' => 'body_only',
        'reply_text_mode' => 'ai',
        'reply_prompt_override' => 'Responda com uma frase curta e emoji coerente com a foto.',
    ]);

    expect($settings)->toBeInstanceOf(EventMediaIntelligenceSetting::class)
        ->and($settings->enabled)->toBeTrue()
        ->and($settings->model_key)->toBe('Qwen/Qwen2.5-VL-7B-Instruct')
        ->and($settings->mode)->toBe('gate')
        ->and($settings->timeout_ms)->toBe(9000)
        ->and($settings->context_scope)->toBe('image_only')
        ->and($settings->reply_scope)->toBe('image_and_text_context')
        ->and($settings->normalized_text_context_mode)->toBe('body_only')
        ->and($settings->reply_text_mode)->toBe('ai')
        ->and($settings->reply_text_enabled)->toBeTrue()
        ->and($settings->reply_prompt_override)->toBe('Responda com uma frase curta e emoji coerente com a foto.');

    $this->assertDatabaseHas('event_media_intelligence_settings', [
        'event_id' => $event->id,
        'enabled' => true,
        'provider_key' => 'vllm',
        'model_key' => 'Qwen/Qwen2.5-VL-7B-Instruct',
        'mode' => 'gate',
        'prompt_version' => 'wedding-v2',
        'fallback_mode' => 'review',
        'require_json_output' => true,
        'context_scope' => 'image_only',
        'reply_scope' => 'image_and_text_context',
        'normalized_text_context_mode' => 'body_only',
        'reply_text_mode' => 'ai',
        'reply_text_enabled' => true,
    ]);
});

it('supports openrouter as an optional provider in the settings action', function () {
    $event = Event::factory()->create();

    $settings = app(UpsertEventMediaIntelligenceSettingsAction::class)->execute($event, [
        'enabled' => true,
        'provider_key' => 'openrouter',
        'model_key' => 'openai/gpt-4.1-mini',
        'mode' => 'enrich_only',
        'prompt_version' => 'foundation-v1',
        'approval_prompt' => 'Avalie e retorne JSON.',
        'caption_style_prompt' => 'Legenda curta.',
        'response_schema_version' => 'foundation-v1',
        'timeout_ms' => 12000,
        'fallback_mode' => 'review',
        'require_json_output' => true,
        'reply_text_mode' => 'disabled',
        'reply_prompt_override' => null,
    ]);

    expect($settings->provider_key)->toBe('openrouter')
        ->and($settings->model_key)->toBe('openai/gpt-4.1-mini');
});

it('supports fixed random automatic replies with per-event templates', function () {
    $event = Event::factory()->create();

    $settings = app(UpsertEventMediaIntelligenceSettingsAction::class)->execute($event, [
        'enabled' => true,
        'provider_key' => 'vllm',
        'model_key' => 'Qwen/Qwen2.5-VL-3B-Instruct',
        'mode' => 'enrich_only',
        'prompt_version' => 'foundation-v1',
        'approval_prompt' => 'Avalie e retorne JSON.',
        'caption_style_prompt' => 'Legenda curta.',
        'response_schema_version' => 'foundation-v1',
        'timeout_ms' => 12000,
        'fallback_mode' => 'review',
        'require_json_output' => true,
        'reply_text_mode' => 'fixed_random',
        'reply_fixed_templates' => [
            'Memorias que fazem o coracao sorrir! 🎉📸',
            'Momento de risadas e lembrancas! 📱🎉',
        ],
        'reply_prompt_override' => null,
    ]);

    expect($settings->reply_text_mode)->toBe('fixed_random')
        ->and($settings->reply_text_enabled)->toBeTrue()
        ->and($settings->reply_fixed_templates_json)->toBe([
            'Memorias que fazem o coracao sorrir! 🎉📸',
            'Momento de risadas e lembrancas! 📱🎉',
        ]);
});

it('keeps compatibility by deriving ai mode from legacy reply_text_enabled', function () {
    $event = Event::factory()->create();

    $settings = app(UpsertEventMediaIntelligenceSettingsAction::class)->execute($event, [
        'enabled' => true,
        'provider_key' => 'vllm',
        'model_key' => 'Qwen/Qwen2.5-VL-3B-Instruct',
        'mode' => 'enrich_only',
        'prompt_version' => 'foundation-v1',
        'approval_prompt' => 'Avalie e retorne JSON.',
        'caption_style_prompt' => 'Legenda curta.',
        'response_schema_version' => 'foundation-v1',
        'timeout_ms' => 12000,
        'fallback_mode' => 'review',
        'require_json_output' => true,
        'reply_text_enabled' => true,
        'reply_prompt_override' => null,
    ]);

    expect($settings->reply_text_mode)->toBe('ai')
        ->and($settings->reply_text_enabled)->toBeTrue();
});
