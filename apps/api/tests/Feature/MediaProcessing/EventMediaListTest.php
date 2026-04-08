<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\InboundMedia\Models\ChannelWebhookLog;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\ContentModeration\Models\EventMediaSafetyEvaluation;
use App\Modules\MediaIntelligence\Models\EventMediaVlmEvaluation;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\MediaProcessingRun;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use App\Modules\Telegram\Models\TelegramMessageFeedback;
use App\Modules\WhatsApp\Enums\MessageDirection;
use App\Modules\WhatsApp\Enums\MessageStatus;
use App\Modules\WhatsApp\Enums\MessageType;
use App\Modules\WhatsApp\Models\WhatsAppChat;
use App\Modules\WhatsApp\Models\WhatsAppDispatchLog;
use App\Modules\WhatsApp\Models\WhatsAppInboundEvent;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Models\WhatsAppMessageFeedback;

it('lists event media with a paginated frontend friendly schema', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'live', 'is_enabled' => true]);

    $message = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-evt-media',
        'message_type' => 'image',
        'sender_phone' => '5511999999999',
        'sender_name' => 'Maria',
        'status' => 'received',
        'received_at' => now(),
    ]);

    EventMedia::factory()->published()->count(2)->create([
        'event_id' => $event->id,
        'inbound_message_id' => $message->id,
        'source_type' => 'whatsapp',
    ]);

    $response = $this->apiGet("/events/{$event->id}/media?per_page=2");

    $this->assertApiPaginated($response);
    $response->assertJsonPath('data.0.channel', 'whatsapp')
        ->assertJsonPath('data.0.status', 'published')
        ->assertJsonPath('data.0.sender_name', 'Maria');
});

it('shows detailed media payload with preview and original asset urls', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'media_type' => 'video',
        'original_filename' => 'clip.mp4',
        'mime_type' => 'video/mp4',
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $media->id,
        'variant_key' => 'gallery',
        'disk' => 'public',
        'path' => 'events/'.$event->id.'/gallery/clip.mp4',
        'mime_type' => 'video/mp4',
        'width' => 1280,
        'height' => 720,
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $media->id,
        'variant_key' => 'thumb',
        'disk' => 'public',
        'path' => 'events/'.$event->id.'/thumb/clip.jpg',
        'mime_type' => 'image/jpeg',
        'width' => 640,
        'height' => 360,
    ]);

    $response = $this->apiGet("/media/{$media->id}");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.id', $media->id)
        ->assertJsonPath('data.media_type', 'video')
        ->assertJsonPath('data.mime_type', 'video/mp4')
        ->assertJsonPath('data.preview_url', rtrim((string) config('app.url'), '/')."/storage/events/{$event->id}/gallery/clip.mp4")
        ->assertJsonPath('data.original_url', rtrim((string) config('app.url'), '/')."/storage/events/{$event->id}/originals/clip.mp4")
        ->assertJsonPath('data.variants.0.variant_key', 'gallery');
});

it('prefers fast preview assets and exposes enriched processing runs in the detail payload', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'media_type' => 'image',
        'original_disk' => 'public',
        'original_path' => "events/{$event->id}/originals/foto.jpg",
        'original_filename' => 'foto.jpg',
        'mime_type' => 'image/jpeg',
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $media->id,
        'variant_key' => 'fast_preview',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$media->id}/fast_preview.webp",
        'mime_type' => 'image/webp',
        'width' => 512,
        'height' => 341,
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $media->id,
        'variant_key' => 'gallery',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$media->id}/gallery.webp",
        'mime_type' => 'image/webp',
        'width' => 1600,
        'height' => 1067,
    ]);

    MediaProcessingRun::query()->create([
        'event_media_id' => $media->id,
        'run_type' => 'variants',
        'stage_key' => 'variants',
        'provider_key' => 'intervention-image',
        'provider_version' => 'v4',
        'model_key' => 'intervention-image-v4',
        'model_snapshot' => 'intervention-image-v4',
        'input_ref' => "events/{$event->id}/variants/{$media->id}/fast_preview.webp",
        'decision_key' => 'generated',
        'queue_name' => 'media-fast',
        'worker_ref' => 'worker-a',
        'result_json' => ['variant_keys' => ['fast_preview', 'gallery']],
        'metrics_json' => ['generated_count' => 2],
        'cost_units' => 0.1250,
        'idempotency_key' => "variants:{$media->id}",
        'status' => 'completed',
        'attempts' => 1,
        'failure_class' => null,
        'started_at' => now()->subSecond(),
        'finished_at' => now(),
    ]);

    $response = $this->apiGet("/media/{$media->id}");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.preview_url', rtrim((string) config('app.url'), '/')."/storage/events/{$event->id}/variants/{$media->id}/fast_preview.webp")
        ->assertJsonPath('data.processing_runs.0.queue_name', 'media-fast')
        ->assertJsonPath('data.processing_runs.0.worker_ref', 'worker-a')
        ->assertJsonPath('data.processing_runs.0.provider_version', 'v4')
        ->assertJsonPath('data.processing_runs.0.model_snapshot', 'intervention-image-v4')
        ->assertJsonPath('data.processing_runs.0.cost_units', 0.125)
        ->assertJsonPath('data.processing_runs.0.failure_class', null);
});

it('shows the latest safety and vlm evaluations in the media detail payload', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'media_type' => 'image',
        'moderation_status' => 'pending',
        'safety_status' => 'pass',
        'vlm_status' => 'completed',
        'caption' => 'Entrada especial na festa.',
    ]);

    EventMediaSafetyEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'decision' => 'review',
        'review_required' => true,
        'blocked' => false,
        'reason_codes_json' => ['violence'],
        'category_scores_json' => ['nudity' => 0.02, 'violence' => 0.61],
        'completed_at' => now()->subMinute(),
    ]);

    $latestSafety = EventMediaSafetyEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'decision' => 'pass',
        'review_required' => false,
        'blocked' => false,
        'reason_codes_json' => [],
        'category_scores_json' => ['nudity' => 0.01, 'violence' => 0.03],
        'provider_categories_json' => ['violence' => false, 'sexual' => false],
        'provider_category_scores_json' => ['violence' => 0.03, 'sexual' => 0.01],
        'provider_category_input_types_json' => ['violence' => ['image']],
        'normalized_provider_json' => [
            'categories' => ['violence' => false, 'sexual' => false],
            'category_scores' => ['violence' => 0.03, 'sexual' => 0.01],
            'category_applied_input_types' => ['violence' => ['image']],
            'input_path_used' => 'image_url',
        ],
        'request_payload_json' => ['model' => 'omni-moderation-latest'],
        'completed_at' => now(),
    ]);

    EventMediaVlmEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'decision' => 'review',
        'reason' => 'Imagem precisa de revisao.',
        'short_caption' => 'Legenda antiga.',
        'tags_json' => ['fila'],
        'completed_at' => now()->subMinute(),
    ]);

    $latestVlm = EventMediaVlmEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'decision' => 'approve',
        'reason' => 'Imagem compativel com o evento.',
        'short_caption' => 'Entrada especial na festa.',
        'reply_text' => 'Memorias que fazem o coracao sorrir! 🎉📸',
        'tags_json' => ['festa', 'retrato'],
        'request_payload_json' => ['model' => 'openai/gpt-4.1-mini'],
        'prompt_context_json' => [
            'template' => 'Use {nome_do_evento} quando fizer sentido.',
            'variables' => ['nome_do_evento' => $event->title],
            'resolved' => 'Use '.$event->title.' quando fizer sentido.',
        ],
        'completed_at' => now(),
    ]);

    $response = $this->apiGet("/media/{$media->id}");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.latest_safety_evaluation.id', $latestSafety->id)
        ->assertJsonPath('data.latest_safety_evaluation.decision', 'pass')
        ->assertJsonPath('data.latest_safety_evaluation.category_scores.violence', 0.03)
        ->assertJsonPath('data.latest_safety_evaluation.provider_category_scores.violence', 0.03)
        ->assertJsonPath('data.latest_safety_evaluation.provider_category_input_types.violence.0', 'image')
        ->assertJsonPath('data.latest_safety_evaluation.normalized_provider.input_path_used', 'image_url')
        ->assertJsonPath('data.latest_safety_evaluation.request_payload.model', 'omni-moderation-latest')
        ->assertJsonPath('data.caption_source_hint', 'vlm')
        ->assertJsonPath('data.latest_vlm_evaluation.id', $latestVlm->id)
        ->assertJsonPath('data.latest_vlm_evaluation.decision', 'approve')
        ->assertJsonPath('data.latest_vlm_evaluation.reason', 'Imagem compativel com o evento.')
        ->assertJsonPath('data.latest_vlm_evaluation.short_caption', 'Entrada especial na festa.')
        ->assertJsonPath('data.latest_vlm_evaluation.reply_text', 'Memorias que fazem o coracao sorrir! 🎉📸')
        ->assertJsonPath('data.latest_vlm_evaluation.tags.0', 'festa')
        ->assertJsonPath('data.latest_vlm_evaluation.request_payload.model', 'openai/gpt-4.1-mini')
        ->assertJsonPath('data.latest_vlm_evaluation.prompt_context.variables.nome_do_evento', $event->title);
});

it('shows an aggregated ai debug payload with trace ids, provider logs and channel feedback logs', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento Debug IA',
    ]);

    $traceId = 'trace-whatsapp-debug-001';

    $inboundMessage = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'zapi',
        'trace_id' => $traceId,
        'message_id' => '2A20028071DA23E04188',
        'message_type' => 'image',
        'chat_external_id' => '120363499999999999-group',
        'sender_external_id' => '11111111111111@lid',
        'sender_phone' => '554899991111',
        'sender_name' => 'Participante Debug',
        'body_text' => 'Foto para homologacao',
        'media_url' => 'https://cdn.fixture.test/debug.jpg',
        'normalized_payload_json' => [
            '_event_context' => [
                'event_id' => $event->id,
                'trace_id' => $traceId,
                'provider_message_id' => '2A20028071DA23E04188',
                'chat_external_id' => '120363499999999999-group',
                'sender_external_id' => '11111111111111@lid',
                'intake_source' => 'whatsapp_group',
            ],
        ],
        'status' => 'processed',
        'received_at' => now()->subMinute(),
        'processed_at' => now()->subSecond(),
    ]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $inboundMessage->id,
        'source_type' => 'whatsapp_group',
        'caption' => 'Entrada especial no evento.',
    ]);

    ChannelWebhookLog::query()->create([
        'event_channel_id' => null,
        'provider' => 'zapi',
        'provider_update_id' => 'upd-debug-1',
        'trace_id' => $traceId,
        'message_id' => '2A20028071DA23E04188',
        'detected_type' => 'image',
        'routing_status' => 'normalized',
        'payload_json' => ['type' => 'ReceivedCallback'],
        'inbound_message_id' => $inboundMessage->id,
    ]);

    $instance = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $organization->id,
        'external_instance_id' => 'INSTANCE-DEBUG-001',
    ]);

    WhatsAppInboundEvent::query()->create([
        'instance_id' => $instance->id,
        'provider_key' => 'zapi',
        'trace_id' => $traceId,
        'provider_message_id' => '2A20028071DA23E04188',
        'event_type' => 'message',
        'payload_json' => ['type' => 'ReceivedCallback'],
        'normalized_json' => ['message_id' => '2A20028071DA23E04188'],
        'processing_status' => 'processed',
        'received_at' => now()->subMinute(),
        'processed_at' => now()->subSecond(),
    ]);

    $chat = WhatsAppChat::query()->create([
        'instance_id' => $instance->id,
        'external_chat_id' => '120363499999999999-group',
        'type' => 'group',
        'group_id' => '120363499999999999-group',
        'display_name' => 'Grupo Debug',
        'is_group' => true,
    ]);

    $outboundMessage = WhatsAppMessage::query()->create([
        'instance_id' => $instance->id,
        'chat_id' => $chat->id,
        'direction' => MessageDirection::Outbound,
        'provider_message_id' => 'WA-OUT-001',
        'reply_to_provider_message_id' => '2A20028071DA23E04188',
        'type' => MessageType::Text,
        'text_body' => 'Memorias que fazem o coracao sorrir! 🎉📸',
        'status' => MessageStatus::Sent,
        'recipient_phone' => '120363499999999999-group',
        'sent_at' => now(),
    ]);

    WhatsAppDispatchLog::query()->create([
        'instance_id' => $instance->id,
        'message_id' => $outboundMessage->id,
        'provider_key' => 'zapi',
        'endpoint_used' => '/send-text',
        'request_json' => [
            'phone' => '120363499999999999-group',
            'messageId' => '2A20028071DA23E04188',
        ],
        'response_json' => [
            'messageId' => 'WA-OUT-001',
        ],
        'http_status' => 200,
        'success' => true,
        'duration_ms' => 412,
    ]);

    EventMediaSafetyEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'provider_key' => 'openai',
        'model_key' => 'omni-moderation-latest',
        'decision' => 'pass',
        'request_payload_json' => ['model' => 'omni-moderation-latest'],
        'raw_response_json' => ['results' => [['flagged' => false]]],
        'normalized_provider_json' => ['input_path_used' => 'data_url'],
        'completed_at' => now(),
    ]);

    EventMediaVlmEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'provider_key' => 'openrouter',
        'model_key' => 'openai/gpt-4.1-mini',
        'decision' => 'approve',
        'short_caption' => 'Entrada especial no evento.',
        'reply_text' => 'Memorias que fazem o coracao sorrir! 🎉📸',
        'request_payload_json' => ['model' => 'openai/gpt-4.1-mini'],
        'raw_response_json' => ['reply_text' => 'Memorias que fazem o coracao sorrir! 🎉📸'],
        'prompt_context_json' => [
            'template' => 'Use {nome_do_evento} quando fizer sentido.',
            'variables' => ['nome_do_evento' => $event->title],
            'resolved' => 'Use Evento Debug IA quando fizer sentido.',
        ],
        'completed_at' => now(),
    ]);

    WhatsAppMessageFeedback::query()->create([
        'event_id' => $event->id,
        'instance_id' => $instance->id,
        'trace_id' => $traceId,
        'inbound_message_id' => $inboundMessage->id,
        'event_media_id' => $media->id,
        'outbound_message_id' => $outboundMessage->id,
        'inbound_provider_message_id' => '2A20028071DA23E04188',
        'chat_external_id' => '120363499999999999-group',
        'sender_external_id' => '11111111111111@lid',
        'feedback_kind' => 'reply',
        'feedback_phase' => 'published',
        'status' => 'sent',
        'reply_text' => 'Memorias que fazem o coracao sorrir! 🎉📸',
        'resolution_json' => [
            'mode' => 'ai',
            'source' => 'vlm',
            'reply_text' => 'Memorias que fazem o coracao sorrir! 🎉📸',
        ],
        'attempted_at' => now()->subSecond(),
        'completed_at' => now(),
    ]);

    TelegramMessageFeedback::query()->create([
        'event_id' => $event->id,
        'event_channel_id' => null,
        'trace_id' => $traceId,
        'inbound_message_id' => $inboundMessage->id,
        'event_media_id' => $media->id,
        'inbound_provider_message_id' => '81',
        'chat_external_id' => '9007199254740991',
        'sender_external_id' => '9007199254740991',
        'feedback_kind' => 'reply',
        'feedback_phase' => 'published',
        'status' => 'sent',
        'reply_text' => 'Memorias que fazem o coracao sorrir! 🎉📸',
        'resolution_json' => [
            'mode' => 'ai',
            'source' => 'vlm',
        ],
        'attempted_at' => now()->subSecond(),
        'completed_at' => now(),
    ]);

    $response = $this->apiGet("/media/{$media->id}/ia-debug");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.trace_id', $traceId)
        ->assertJsonPath('data.inbound.message.trace_id', $traceId)
        ->assertJsonPath('data.inbound.webhook_logs.0.trace_id', $traceId)
        ->assertJsonPath('data.inbound.whatsapp_events.0.trace_id', $traceId)
        ->assertJsonPath('data.safety.request_payload.model', 'omni-moderation-latest')
        ->assertJsonPath('data.vlm.request_payload.model', 'openai/gpt-4.1-mini')
        ->assertJsonPath('data.vlm.prompt_context.variables.nome_do_evento', $event->title)
        ->assertJsonPath('data.feedback.whatsapp.0.trace_id', $traceId)
        ->assertJsonPath('data.feedback.whatsapp_dispatch_logs.0.endpoint_used', '/send-text')
        ->assertJsonPath('data.feedback.telegram.0.trace_id', $traceId);
});

it('marks caption source as human when the stored caption differs from the latest vlm short caption', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'caption' => 'Legenda humana preservada.',
        'vlm_status' => 'completed',
    ]);

    EventMediaVlmEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'decision' => 'approve',
        'reason' => 'Imagem compativel com o evento.',
        'short_caption' => 'Legenda sugerida pela IA.',
        'tags_json' => ['festa'],
        'completed_at' => now(),
    ]);

    $response = $this->apiGet("/media/{$media->id}");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.caption_source_hint', 'human');
});
