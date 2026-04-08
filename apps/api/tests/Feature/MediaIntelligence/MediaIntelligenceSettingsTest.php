<?php

use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaIntelligence\Models\MediaIntelligenceGlobalSetting;

it('returns inherited media intelligence settings for an event when none were persisted yet', function () {
    [$user, $organization] = $this->actingAsOwner();

    MediaIntelligenceGlobalSetting::query()->create([
        'id' => 1,
        'enabled' => true,
        'provider_key' => 'openrouter',
        'model_key' => 'openai/gpt-4.1-mini',
        'mode' => 'gate',
        'context_scope' => 'image_only',
        'reply_scope' => 'image_and_text_context',
        'normalized_text_context_mode' => 'caption_only',
        'response_schema_version' => 'contextual-v2',
        'contextual_policy_preset_key' => 'casamento_equilibrado',
        'allow_alcohol' => true,
        'allow_tobacco' => false,
        'required_people_context' => 'required',
        'blocked_terms_json' => ['mascaras'],
        'allowed_exceptions_json' => ['brinde com espumante'],
        'freeform_instruction' => 'Prefira review quando a cena estiver ambigua.',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiGet("/events/{$event->id}/media-intelligence/settings");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.provider_key', 'openrouter')
        ->assertJsonPath('data.model_key', 'openai/gpt-4.1-mini')
        ->assertJsonPath('data.mode', 'gate')
        ->assertJsonPath('data.require_json_output', true)
        ->assertJsonPath('data.context_scope', 'image_only')
        ->assertJsonPath('data.reply_scope', 'image_and_text_context')
        ->assertJsonPath('data.normalized_text_context_mode', 'caption_only')
        ->assertJsonPath('data.response_schema_version', 'contextual-v2')
        ->assertJsonPath('data.contextual_policy_preset_key', 'casamento_equilibrado')
        ->assertJsonPath('data.allow_alcohol', true)
        ->assertJsonPath('data.allow_tobacco', false)
        ->assertJsonPath('data.required_people_context', 'required')
        ->assertJsonPath('data.blocked_terms.0', 'mascaras')
        ->assertJsonPath('data.allowed_exceptions.0', 'brinde com espumante')
        ->assertJsonPath('data.freeform_instruction', 'Prefira review quando a cena estiver ambigua.')
        ->assertJsonPath('data.inherits_global', true)
        ->assertJsonPath('data.reply_text_mode', 'disabled')
        ->assertJsonPath('data.reply_text_enabled', false)
        ->assertJsonPath('data.reply_fixed_templates', [])
        ->assertJsonPath('data.reply_prompt_override', null);
});

it('updates media intelligence settings for an event with structured contextual policy fields', function () {
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
        'freeform_instruction' => 'Aceite beca, palco e plateia. Se a imagem estiver ambigua, use review.',
        'caption_style_prompt' => 'Legenda curta e positiva.',
        'response_schema_version' => 'contextual-v2',
        'timeout_ms' => 9000,
        'fallback_mode' => 'review',
        'require_json_output' => true,
        'context_scope' => 'image_only',
        'reply_scope' => 'image_and_text_context',
        'normalized_text_context_mode' => 'body_only',
        'contextual_policy_preset_key' => 'formatura',
        'allow_alcohol' => true,
        'allow_tobacco' => false,
        'required_people_context' => 'required',
        'blocked_terms' => ['mascaras'],
        'allowed_exceptions' => ['brinde com champagne'],
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
        ->assertJsonPath('data.context_scope', 'image_only')
        ->assertJsonPath('data.reply_scope', 'image_and_text_context')
        ->assertJsonPath('data.normalized_text_context_mode', 'body_only')
        ->assertJsonPath('data.response_schema_version', 'contextual-v2')
        ->assertJsonPath('data.contextual_policy_preset_key', 'formatura')
        ->assertJsonPath('data.allow_alcohol', true)
        ->assertJsonPath('data.allow_tobacco', false)
        ->assertJsonPath('data.required_people_context', 'required')
        ->assertJsonPath('data.blocked_terms.0', 'mascaras')
        ->assertJsonPath('data.allowed_exceptions.0', 'brinde com champagne')
        ->assertJsonPath('data.freeform_instruction', 'Aceite beca, palco e plateia. Se a imagem estiver ambigua, use review.')
        ->assertJsonPath('data.inherits_global', false)
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
        'context_scope' => 'image_only',
        'reply_scope' => 'image_and_text_context',
        'normalized_text_context_mode' => 'body_only',
        'response_schema_version' => 'contextual-v2',
        'contextual_policy_preset_key' => 'formatura',
        'allow_alcohol' => true,
        'allow_tobacco' => false,
        'required_people_context' => 'required',
        'freeform_instruction' => 'Aceite beca, palco e plateia. Se a imagem estiver ambigua, use review.',
        'reply_text_mode' => 'ai',
        'reply_text_enabled' => true,
    ]);
});

it('resets media intelligence settings to inherit the global policy for an event', function () {
    [$user, $organization] = $this->actingAsOwner();

    MediaIntelligenceGlobalSetting::query()->create([
        'id' => 1,
        'enabled' => true,
        'provider_key' => 'openrouter',
        'model_key' => 'openai/gpt-4.1-mini',
        'mode' => 'gate',
        'context_scope' => 'image_only',
        'reply_scope' => 'image_and_text_context',
        'normalized_text_context_mode' => 'body_plus_caption',
        'response_schema_version' => 'contextual-v2',
        'contextual_policy_preset_key' => 'corporativo_restrito',
        'allow_alcohol' => false,
        'allow_tobacco' => false,
        'required_people_context' => 'required',
        'blocked_terms_json' => ['charutos'],
        'allowed_exceptions_json' => ['palestrante no palco'],
        'freeform_instruction' => 'Aceite palco e plateia, mas nao objetos isolados.',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    EventMediaIntelligenceSetting::factory()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'provider_key' => 'vllm',
        'mode' => 'enrich_only',
        'contextual_policy_preset_key' => 'homologacao_livre',
        'allow_alcohol' => true,
    ]);

    $response = $this->apiPatch("/events/{$event->id}/media-intelligence/settings", [
        'inherit_global' => true,
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.provider_key', 'openrouter')
        ->assertJsonPath('data.mode', 'gate')
        ->assertJsonPath('data.contextual_policy_preset_key', 'corporativo_restrito')
        ->assertJsonPath('data.allow_alcohol', false)
        ->assertJsonPath('data.allow_tobacco', false)
        ->assertJsonPath('data.required_people_context', 'required')
        ->assertJsonPath('data.blocked_terms.0', 'charutos')
        ->assertJsonPath('data.allowed_exceptions.0', 'palestrante no palco')
        ->assertJsonPath('data.freeform_instruction', 'Aceite palco e plateia, mas nao objetos isolados.')
        ->assertJsonPath('data.inherits_global', true);

    $this->assertDatabaseMissing('event_media_intelligence_settings', [
        'event_id' => $event->id,
    ]);
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
        'caption_style_prompt' => 'Legenda curta e positiva.',
        'response_schema_version' => 'contextual-v2',
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
        'caption_style_prompt' => 'Teste',
        'response_schema_version' => 'contextual-v2',
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
        'caption_style_prompt' => 'Teste',
        'response_schema_version' => 'contextual-v2',
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
        'caption_style_prompt' => 'Teste',
        'response_schema_version' => 'contextual-v2',
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
        'caption_style_prompt' => 'Teste',
        'response_schema_version' => 'contextual-v2',
        'timeout_ms' => 9000,
        'fallback_mode' => 'review',
        'require_json_output' => true,
        'reply_text_enabled' => false,
        'reply_prompt_override' => null,
    ]);

    $this->assertApiForbidden($response);

    expect(EventMediaIntelligenceSetting::query()->count())->toBe(0);
});
