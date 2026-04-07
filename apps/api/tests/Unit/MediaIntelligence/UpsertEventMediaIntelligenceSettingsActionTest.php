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
    ]);

    expect($settings)->toBeInstanceOf(EventMediaIntelligenceSetting::class)
        ->and($settings->enabled)->toBeTrue()
        ->and($settings->model_key)->toBe('Qwen/Qwen2.5-VL-7B-Instruct')
        ->and($settings->mode)->toBe('gate')
        ->and($settings->timeout_ms)->toBe(9000);

    $this->assertDatabaseHas('event_media_intelligence_settings', [
        'event_id' => $event->id,
        'enabled' => true,
        'provider_key' => 'vllm',
        'model_key' => 'Qwen/Qwen2.5-VL-7B-Instruct',
        'mode' => 'gate',
        'prompt_version' => 'wedding-v2',
        'fallback_mode' => 'review',
        'require_json_output' => true,
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
    ]);

    expect($settings->provider_key)->toBe('openrouter')
        ->and($settings->model_key)->toBe('openai/gpt-4.1-mini');
});
