<?php

use App\Modules\Events\Models\Event;
use App\Modules\InboundMedia\Models\InboundMessage;
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
        'reply_text' => 'Memorias que fazem o coracao sorrir!',
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
        'reply_text' => 'Momento especial!',
        'request_payload_json' => ['model' => 'openai/gpt-4.1-mini'],
        'raw_response_json' => ['reply_text' => 'Momento especial!'],
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
        ->assertJsonPath('data.prompt_template', 'Use {nome_do_evento}.')
        ->assertJsonPath('data.prompt_resolved', 'Use Evento Teste.')
        ->assertJsonPath('data.prompt_variables.nome_do_evento', 'Evento Teste')
        ->assertJsonPath('data.preset_name', 'Festas vibrantes')
        ->assertJsonPath('data.normalized_text_context', 'Legenda enviada pelo convidado')
        ->assertJsonPath('data.normalized_text_context_mode', 'caption_only')
        ->assertJsonPath('data.context_scope', 'image_only')
        ->assertJsonPath('data.reply_scope', 'image_and_text_context')
        ->assertJsonPath('data.reply_text', 'Momento especial!')
        ->assertJsonPath('data.request_payload.model', 'openai/gpt-4.1-mini')
        ->assertJsonPath('data.response_payload.reply_text', 'Momento especial!');
});
