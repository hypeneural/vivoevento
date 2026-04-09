<?php

use App\Modules\Events\Models\Event;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaIntelligence\Models\EventMediaVlmEvaluation;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\MediaProcessingRun;
use Illuminate\Support\Str;

it('lists only events that already have media reply history', function () {
    [$user] = $this->actingAsSuperAdmin();

    $eventWithHistory = Event::factory()->create([
        'title' => 'Formatura 2026',
    ]);
    $eventWithoutHistory = Event::factory()->create([
        'title' => 'Evento sem historico',
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $eventWithHistory->id,
        'vlm_status' => 'completed',
    ]);

    EventMediaVlmEvaluation::factory()->create([
        'event_id' => $eventWithHistory->id,
        'event_media_id' => $media->id,
    ]);

    $response = $this->apiGet('/ia/respostas-de-midia/eventos');

    $this->assertApiSuccess($response);
    expect($response->json('data'))->toHaveCount(1);
    $response->assertJsonPath('data.0.id', $eventWithHistory->id)
        ->assertJsonPath('data.0.title', 'Formatura 2026');

    expect(collect($response->json('data'))->pluck('id')->all())
        ->not->toContain($eventWithoutHistory->id);
});

it('filters real event history by event, preset name, sender query and provider', function () {
    [$user] = $this->actingAsSuperAdmin();

    $event = Event::factory()->create([
        'title' => 'Casamento Ana e Pedro',
    ]);
    $otherEvent = Event::factory()->create([
        'title' => 'Corporativo XPTO',
    ]);

    $matchingInbound = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'zapi',
        'message_id' => 'wamid.match.' . Str::random(6),
        'message_type' => 'image',
        'sender_phone' => '5548998483594',
        'sender_name' => 'Anderson Marques',
        'sender_external_id' => '5548998483594',
        'trace_id' => 'trace-match',
        'status' => 'processed',
        'received_at' => now(),
    ]);

    $matchingMedia = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $matchingInbound->id,
        'vlm_status' => 'completed',
        'source_type' => 'whatsapp',
        'source_label' => 'WhatsApp',
    ]);

    EventMediaVlmEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $matchingMedia->id,
        'provider_key' => 'openrouter',
        'model_key' => 'openai/gpt-4.1-mini',
        'reason' => 'A imagem combina com a celebracao.',
        'reason_code' => 'context.match.event',
        'matched_policies_json' => ['preset:casamento_equilibrado'],
        'matched_exceptions_json' => ['brinde com espumante'],
        'input_scope_used' => 'image_and_text_context',
        'input_types_considered_json' => ['image', 'text'],
        'confidence_band' => 'high',
        'publish_eligibility' => 'auto_publish',
        'reply_text' => 'Memorias que fazem o coracao sorrir!',
        'policy_snapshot_json' => [
            'contextual_policy_preset_key' => 'casamento_equilibrado',
        ],
        'policy_sources_json' => [
            'allow_alcohol' => 'preset',
        ],
        'prompt_context_json' => [
            'template' => 'Use {nome_do_evento}.',
            'variables' => ['nome_do_evento' => 'Casamento Ana e Pedro'],
            'resolved' => 'Use Casamento Ana e Pedro.',
            'preset_name' => 'Casamento romantico',
            'preset_id' => 10,
            'preset_source' => 'event',
            'instruction_source' => 'event',
            'normalized_text_context' => 'Texto recebido do convidado',
            'normalized_text_context_mode' => 'body_only',
            'context_scope' => 'image_and_text_context',
            'reply_scope' => 'image_only',
        ],
    ]);

    MediaProcessingRun::query()->create([
        'event_media_id' => $matchingMedia->id,
        'run_type' => 'async',
        'stage_key' => 'vlm',
        'provider_key' => 'openrouter',
        'provider_version' => 'openrouter-v1',
        'model_key' => 'openai/gpt-4.1-mini',
        'decision_key' => 'approve',
        'queue_name' => 'media-vlm',
        'status' => 'completed',
        'attempts' => 1,
        'started_at' => now()->subSeconds(3),
        'finished_at' => now(),
    ]);

    $otherInbound = InboundMessage::query()->create([
        'event_id' => $otherEvent->id,
        'provider' => 'zapi',
        'message_id' => 'wamid.other.' . Str::random(6),
        'message_type' => 'image',
        'sender_phone' => '5511999999999',
        'sender_name' => 'Outro contato',
        'sender_external_id' => '5511999999999',
        'trace_id' => 'trace-other',
        'status' => 'processed',
        'received_at' => now(),
    ]);

    $otherMedia = EventMedia::factory()->create([
        'event_id' => $otherEvent->id,
        'inbound_message_id' => $otherInbound->id,
        'vlm_status' => 'failed',
    ]);

    EventMediaVlmEvaluation::factory()->create([
        'event_id' => $otherEvent->id,
        'event_media_id' => $otherMedia->id,
        'provider_key' => 'vllm',
        'model_key' => 'Qwen/Qwen2.5-VL-3B-Instruct',
        'prompt_context_json' => [
            'template' => 'Use {nome_do_evento}.',
            'variables' => ['nome_do_evento' => 'Corporativo XPTO'],
            'resolved' => 'Use Corporativo XPTO.',
            'preset_name' => 'Corporativo acolhedor',
        ],
    ]);

    $response = $this->apiGet('/ia/respostas-de-midia/historico-eventos?event_id=' . $event->id . '&provider_key=openrouter&preset_name=romantico&sender_query=483594&status=success&per_page=10');

    $this->assertApiPaginated($response);
    expect($response->json('data'))->toHaveCount(1);
    $response->assertJsonPath('data.0.id', $matchingMedia->id)
        ->assertJsonPath('data.0.event_title', 'Casamento Ana e Pedro')
        ->assertJsonPath('data.0.provider_key', 'openrouter')
        ->assertJsonPath('data.0.reason_code', 'context.match.event')
        ->assertJsonPath('data.0.confidence_band', 'high')
        ->assertJsonPath('data.0.publish_eligibility', 'auto_publish')
        ->assertJsonPath('data.0.effective_media_state', 'published')
        ->assertJsonPath('data.0.context_decision', 'approved')
        ->assertJsonPath('data.0.policy_label', 'Casamento romantico')
        ->assertJsonPath('data.0.policy_inheritance_mode', 'preset')
        ->assertJsonPath('data.0.text_context_summary', 'A decisao usou imagem + texto normalizado.')
        ->assertJsonPath('data.0.human_reason', 'A imagem combina com a celebracao.')
        ->assertJsonPath('data.0.preset_name', 'Casamento romantico')
        ->assertJsonPath('data.0.sender_phone', '5548998483594')
        ->assertJsonPath('data.0.status', 'completed');
});

it('shows a real event history entry with prompt context and payloads', function () {
    [$user] = $this->actingAsSuperAdmin();

    $event = Event::factory()->create([
        'title' => 'Evento Teste',
    ]);

    $inbound = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'zapi',
        'message_id' => 'wamid.show.' . Str::random(6),
        'message_type' => 'image',
        'sender_phone' => '5548998483594',
        'sender_name' => 'Cliente Teste',
        'sender_external_id' => '5548998483594',
        'trace_id' => 'trace-show',
        'status' => 'processed',
        'received_at' => now(),
    ]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $inbound->id,
        'vlm_status' => 'completed',
    ]);

    EventMediaVlmEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'provider_key' => 'openrouter',
        'model_key' => 'openai/gpt-4.1-mini',
        'reason' => 'A camera faz sentido para o evento.',
        'reason_code' => 'context.match.event',
        'matched_policies_json' => ['preset:homologacao_livre'],
        'matched_exceptions_json' => ['camera_fotografica'],
        'input_scope_used' => 'image_only',
        'input_types_considered_json' => ['image'],
        'confidence_band' => 'medium',
        'publish_eligibility' => 'review_only',
        'reply_text' => 'Momento especial!',
        'request_payload_json' => ['model' => 'openai/gpt-4.1-mini'],
        'raw_response_json' => ['reply_text' => 'Momento especial!'],
        'policy_snapshot_json' => [
            'contextual_policy_preset_key' => 'homologacao_livre',
            'allow_alcohol' => true,
        ],
        'policy_sources_json' => [
            'allow_alcohol' => 'preset',
        ],
        'prompt_context_json' => [
            'template' => 'Use {nome_do_evento}.',
            'variables' => ['nome_do_evento' => 'Evento Teste'],
            'resolved' => 'Use Evento Teste.',
            'preset_name' => 'Festas vibrantes',
            'normalized_text_context' => 'Legenda enviada pelo convidado',
            'normalized_text_context_mode' => 'caption_only',
            'context_scope' => 'image_only',
            'reply_scope' => 'image_and_text_context',
        ],
    ]);

    MediaProcessingRun::query()->create([
        'event_media_id' => $media->id,
        'run_type' => 'async',
        'stage_key' => 'vlm',
        'provider_key' => 'openrouter',
        'provider_version' => 'openrouter-v1',
        'model_key' => 'openai/gpt-4.1-mini',
        'decision_key' => 'approve',
        'queue_name' => 'media-vlm',
        'status' => 'completed',
        'attempts' => 1,
        'started_at' => now()->subSeconds(5),
        'finished_at' => now(),
    ]);

    $response = $this->apiGet("/ia/respostas-de-midia/historico-eventos/{$media->id}");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.id', $media->id)
        ->assertJsonPath('data.event_title', 'Evento Teste')
        ->assertJsonPath('data.trace_id', 'trace-show')
        ->assertJsonPath('data.effective_media_state', 'published')
        ->assertJsonPath('data.context_decision', 'approved')
        ->assertJsonPath('data.policy_label', 'Festas vibrantes')
        ->assertJsonPath('data.policy_inheritance_mode', 'preset')
        ->assertJsonPath('data.text_context_summary', 'A decisao usou somente a imagem.')
        ->assertJsonPath('data.human_reason', 'A camera faz sentido para o evento.')
        ->assertJsonPath('data.prompt_template', 'Use {nome_do_evento}.')
        ->assertJsonPath('data.prompt_resolved', 'Use Evento Teste.')
        ->assertJsonPath('data.prompt_variables.nome_do_evento', 'Evento Teste')
        ->assertJsonPath('data.preset_name', 'Festas vibrantes')
        ->assertJsonPath('data.reason', 'A camera faz sentido para o evento.')
        ->assertJsonPath('data.reason_code', 'context.match.event')
        ->assertJsonPath('data.matched_policies.0', 'preset:homologacao_livre')
        ->assertJsonPath('data.matched_exceptions.0', 'camera_fotografica')
        ->assertJsonPath('data.input_scope_used', 'image_only')
        ->assertJsonPath('data.input_types_considered.0', 'image')
        ->assertJsonPath('data.confidence_band', 'medium')
        ->assertJsonPath('data.publish_eligibility', 'review_only')
        ->assertJsonPath('data.policy_snapshot.contextual_policy_preset_key', 'homologacao_livre')
        ->assertJsonPath('data.policy_sources.allow_alcohol', 'preset')
        ->assertJsonPath('data.normalized_text_context', 'Legenda enviada pelo convidado')
        ->assertJsonPath('data.normalized_text_context_mode', 'caption_only')
        ->assertJsonPath('data.context_scope', 'image_only')
        ->assertJsonPath('data.reply_scope', 'image_and_text_context')
        ->assertJsonPath('data.reply_text', 'Momento especial!')
        ->assertJsonPath('data.request_payload.model', 'openai/gpt-4.1-mini')
        ->assertJsonPath('data.response_payload.reply_text', 'Momento especial!');
});

it('filters real event history by reason code, publish eligibility and effective media state', function () {
    [$user] = $this->actingAsSuperAdmin();

    $event = Event::factory()->aiModeration()->create([
        'title' => 'Evento com auditoria completa',
    ]);

    EventContentModerationSetting::factory()->create([
        'event_id' => $event->id,
        'provider_key' => 'openai',
        'mode' => 'enforced',
        'enabled' => true,
    ]);

    EventMediaIntelligenceSetting::factory()->gate()->create([
        'event_id' => $event->id,
        'provider_key' => 'openrouter',
        'enabled' => true,
    ]);

    $publishedInbound = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'zapi',
        'message_id' => 'wamid.audit.published.' . Str::random(6),
        'message_type' => 'image',
        'sender_phone' => '5548991111111',
        'sender_name' => 'Publicado',
        'sender_external_id' => '5548991111111',
        'trace_id' => 'trace-published',
        'status' => 'processed',
        'received_at' => now(),
    ]);

    $publishedMedia = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $publishedInbound->id,
        'safety_status' => 'pass',
        'vlm_status' => 'completed',
    ]);

    EventMediaVlmEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $publishedMedia->id,
        'provider_key' => 'openrouter',
        'model_key' => 'openai/gpt-4.1-mini',
        'reason_code' => 'context.match.event',
        'publish_eligibility' => 'auto_publish',
        'prompt_context_json' => [
            'template' => 'Use {nome_do_evento}.',
            'variables' => ['nome_do_evento' => 'Evento com auditoria completa'],
            'resolved' => 'Use Evento com auditoria completa.',
            'preset_name' => 'Homologacao livre',
        ],
    ]);

    $rejectedInbound = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'zapi',
        'message_id' => 'wamid.audit.rejected.' . Str::random(6),
        'message_type' => 'image',
        'sender_phone' => '5548992222222',
        'sender_name' => 'Rejeitado',
        'sender_external_id' => '5548992222222',
        'trace_id' => 'trace-rejected',
        'status' => 'processed',
        'received_at' => now(),
    ]);

    $rejectedMedia = EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $rejectedInbound->id,
        'safety_status' => 'pass',
        'vlm_status' => 'rejected',
        'publication_status' => 'draft',
    ]);

    EventMediaVlmEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $rejectedMedia->id,
        'provider_key' => 'openrouter',
        'model_key' => 'openai/gpt-4.1-mini',
        'reason_code' => 'context.reject.object',
        'publish_eligibility' => 'reject',
        'reason' => 'O objeto nao faz sentido para o contexto do evento.',
        'prompt_context_json' => [
            'template' => 'Use {nome_do_evento}.',
            'variables' => ['nome_do_evento' => 'Evento com auditoria completa'],
            'resolved' => 'Use Evento com auditoria completa.',
            'preset_name' => 'Corporativo restrito',
        ],
    ]);

    $publishedResponse = $this->apiGet(
        '/ia/respostas-de-midia/historico-eventos?reason_code=context.match.event&publish_eligibility=auto_publish&effective_media_state=published&per_page=10'
    );

    $this->assertApiPaginated($publishedResponse);
    expect($publishedResponse->json('data'))->toHaveCount(1);
    $publishedResponse
        ->assertJsonPath('data.0.id', $publishedMedia->id)
        ->assertJsonPath('data.0.reason_code', 'context.match.event')
        ->assertJsonPath('data.0.publish_eligibility', 'auto_publish')
        ->assertJsonPath('data.0.effective_media_state', 'published');

    $rejectedResponse = $this->apiGet(
        '/ia/respostas-de-midia/historico-eventos?reason_code=context.reject.object&publish_eligibility=reject&effective_media_state=rejected&per_page=10'
    );

    $this->assertApiPaginated($rejectedResponse);
    expect($rejectedResponse->json('data'))->toHaveCount(1);
    $rejectedResponse
        ->assertJsonPath('data.0.id', $rejectedMedia->id)
        ->assertJsonPath('data.0.reason_code', 'context.reject.object')
        ->assertJsonPath('data.0.publish_eligibility', 'reject')
        ->assertJsonPath('data.0.effective_media_state', 'rejected');
});
