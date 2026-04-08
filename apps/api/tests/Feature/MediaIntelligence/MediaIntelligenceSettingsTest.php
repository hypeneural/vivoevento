<?php

use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;

it('returns default media intelligence settings for an event when none were persisted yet', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiGet("/events/{$event->id}/media-intelligence/settings");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.provider_key', 'vllm')
        ->assertJsonPath('data.mode', 'enrich_only')
        ->assertJsonPath('data.require_json_output', true)
        ->assertJsonPath('data.reply_text_mode', 'disabled')
        ->assertJsonPath('data.reply_text_enabled', false)
        ->assertJsonPath('data.reply_fixed_templates', [])
        ->assertJsonPath('data.reply_prompt_override', null);
});

it('updates media intelligence settings for an event', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}/media-intelligence/settings", [
        'enabled' => true,
        'provider_key' => 'vllm',
        'model_key' => 'Qwen/Qwen2.5-VL-7B-Instruct',
        'mode' => 'gate',
        'prompt_version' => 'graduation-v2',
        'approval_prompt' => 'Avalie se a foto combina com a formatura.',
        'caption_style_prompt' => 'Legenda curta e positiva.',
        'response_schema_version' => 'foundation-v1',
        'timeout_ms' => 9000,
        'fallback_mode' => 'review',
        'require_json_output' => true,
        'reply_text_mode' => 'ai',
        'reply_prompt_override' => 'Responda com uma frase curtinha e emoji coerente com a imagem.',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.provider_key', 'vllm')
        ->assertJsonPath('data.model_key', 'Qwen/Qwen2.5-VL-7B-Instruct')
        ->assertJsonPath('data.mode', 'gate')
        ->assertJsonPath('data.prompt_version', 'graduation-v2')
        ->assertJsonPath('data.timeout_ms', 9000)
        ->assertJsonPath('data.reply_text_mode', 'ai')
        ->assertJsonPath('data.reply_text_enabled', true)
        ->assertJsonPath('data.reply_prompt_override', 'Responda com uma frase curtinha e emoji coerente com a imagem.');

    $this->assertDatabaseHas('event_media_intelligence_settings', [
        'event_id' => $event->id,
        'enabled' => true,
        'provider_key' => 'vllm',
        'model_key' => 'Qwen/Qwen2.5-VL-7B-Instruct',
        'mode' => 'gate',
        'prompt_version' => 'graduation-v2',
        'fallback_mode' => 'review',
        'require_json_output' => true,
        'reply_text_mode' => 'ai',
        'reply_text_enabled' => true,
    ]);
});

it('updates media intelligence settings with fixed random replies for an event', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}/media-intelligence/settings", [
        'enabled' => true,
        'provider_key' => 'vllm',
        'model_key' => 'Qwen/Qwen2.5-VL-7B-Instruct',
        'mode' => 'enrich_only',
        'prompt_version' => 'foundation-v1',
        'approval_prompt' => 'Avalie se a foto combina com o evento.',
        'caption_style_prompt' => 'Legenda curta e positiva.',
        'response_schema_version' => 'foundation-v1',
        'timeout_ms' => 9000,
        'fallback_mode' => 'review',
        'require_json_output' => true,
        'reply_text_mode' => 'fixed_random',
        'reply_fixed_templates' => [
            'Memorias que fazem o coracao sorrir! 🎉📸',
            'Momento de risadas e lembrancas! 📱🎉',
        ],
        'reply_prompt_override' => null,
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.reply_text_mode', 'fixed_random')
        ->assertJsonPath('data.reply_text_enabled', true)
        ->assertJsonPath('data.reply_fixed_templates.0', 'Memorias que fazem o coracao sorrir! 🎉📸')
        ->assertJsonPath('data.reply_fixed_templates.1', 'Momento de risadas e lembrancas! 📱🎉');
});

it('accepts openrouter as the media intelligence provider', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}/media-intelligence/settings", [
        'enabled' => true,
        'provider_key' => 'openrouter',
        'model_key' => 'openai/gpt-4.1-mini',
        'mode' => 'enrich_only',
        'prompt_version' => 'foundation-v1',
        'approval_prompt' => 'Avalie a foto e retorne JSON.',
        'caption_style_prompt' => 'Legenda curta e positiva.',
        'response_schema_version' => 'foundation-v1',
        'timeout_ms' => 9000,
        'fallback_mode' => 'review',
        'require_json_output' => true,
        'reply_text_enabled' => false,
        'reply_prompt_override' => null,
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.provider_key', 'openrouter')
        ->assertJsonPath('data.model_key', 'openai/gpt-4.1-mini');
});

it('rejects non-pinned OpenRouter router aliases in saved settings', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}/media-intelligence/settings", [
        'enabled' => true,
        'provider_key' => 'openrouter',
        'model_key' => 'openrouter/auto',
        'mode' => 'enrich_only',
        'prompt_version' => 'foundation-v1',
        'approval_prompt' => 'Teste',
        'caption_style_prompt' => 'Teste',
        'response_schema_version' => 'foundation-v1',
        'timeout_ms' => 9000,
        'fallback_mode' => 'review',
        'require_json_output' => true,
        'reply_text_enabled' => false,
        'reply_prompt_override' => null,
    ]);

    $this->assertApiValidationError($response, ['model_key']);
});

it('rejects non-homologated fixed OpenRouter models in saved settings', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}/media-intelligence/settings", [
        'enabled' => true,
        'provider_key' => 'openrouter',
        'model_key' => 'openai/gpt-4.1-nano',
        'mode' => 'enrich_only',
        'prompt_version' => 'foundation-v1',
        'approval_prompt' => 'Teste',
        'caption_style_prompt' => 'Teste',
        'response_schema_version' => 'foundation-v1',
        'timeout_ms' => 9000,
        'fallback_mode' => 'review',
        'require_json_output' => true,
        'reply_text_enabled' => false,
        'reply_prompt_override' => null,
    ]);

    $this->assertApiValidationError($response, ['model_key']);
});

it('validates that gate mode cannot use skip fallback', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}/media-intelligence/settings", [
        'enabled' => true,
        'provider_key' => 'vllm',
        'model_key' => 'Qwen/Qwen2.5-VL-3B-Instruct',
        'mode' => 'gate',
        'prompt_version' => 'foundation-v1',
        'approval_prompt' => 'Teste',
        'caption_style_prompt' => 'Teste',
        'response_schema_version' => 'foundation-v1',
        'timeout_ms' => 9000,
        'fallback_mode' => 'skip',
        'require_json_output' => true,
        'reply_text_enabled' => false,
        'reply_prompt_override' => null,
    ]);

    $this->assertApiValidationError($response, ['fallback_mode']);
});

it('forbids updating media intelligence settings without permission in the event organization', function () {
    [$user, $organization] = $this->actingAsViewer();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}/media-intelligence/settings", [
        'enabled' => true,
        'provider_key' => 'vllm',
        'model_key' => 'Qwen/Qwen2.5-VL-3B-Instruct',
        'mode' => 'enrich_only',
        'prompt_version' => 'foundation-v1',
        'approval_prompt' => 'Teste',
        'caption_style_prompt' => 'Teste',
        'response_schema_version' => 'foundation-v1',
        'timeout_ms' => 9000,
        'fallback_mode' => 'review',
        'require_json_output' => true,
        'reply_text_enabled' => false,
        'reply_prompt_override' => null,
    ]);

    $this->assertApiForbidden($response);

    expect(EventMediaIntelligenceSetting::query()->count())->toBe(0);
});
