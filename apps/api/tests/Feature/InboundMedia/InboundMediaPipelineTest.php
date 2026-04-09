<?php

use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Models\Event;
use App\Modules\InboundMedia\Jobs\NormalizeInboundMessageJob;
use App\Modules\InboundMedia\Jobs\ProcessInboundWebhookJob;
use App\Modules\InboundMedia\Models\ChannelWebhookLog;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Enums\MediaProcessingStatus;
use App\Modules\MediaProcessing\Jobs\DownloadInboundMediaJob;
use App\Modules\MediaProcessing\Jobs\GenerateMediaVariantsJob;
use App\Modules\MediaProcessing\Jobs\RunModerationJob;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function loadInboundMediaFixture(string $name, array $overrides = []): array
{
    $payload = json_decode(
        file_get_contents(base_path("tests/Fixtures/WhatsApp/ZApi/{$name}.json")),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    return array_replace_recursive($payload, $overrides);
}

it('consumes _event_context in the canonical inbound media pipeline and creates inbound_message plus event_media', function () {
    Storage::fake('public');
    Queue::fake([GenerateMediaVariantsJob::class]);

    Http::fake([
        'https://cdn.fixture.test/*' => Http::response(
            base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBAQEA8QDw8PDw8PDw8PDw8QDxAQFREWFhURFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMsNygtLisBCgoKDg0OGhAQGi0lHyUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAEAAgMBIgACEQEDEQH/xAAXAAEBAQEAAAAAAAAAAAAAAAAAAQID/8QAFhEBAQEAAAAAAAAAAAAAAAAAABEB/9oADAMBAAIQAxAAAAH2gD//xAAZEAEAAwEBAAAAAAAAAAAAAAABAAIREiH/2gAIAQEAAQUCk2x1K//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8BP//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8BP//Z'),
            200,
            ['Content-Type' => 'image/jpeg'],
        ),
    ]);

    $event = Event::factory()->active()->create();
    $channel = EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => 'whatsapp_group',
        'provider' => 'zapi',
        'external_id' => 'GRUPOAUTO',
        'label' => 'WhatsApp grupos',
        'status' => 'active',
        'config_json' => [],
    ]);

    $payload = array_replace_recursive(
        loadInboundMediaFixture('group-image-with-caption'),
        [
            '_event_context' => [
                'event_id' => $event->id,
                'event_channel_id' => $channel->id,
                'intake_source' => 'whatsapp_group',
                'provider_message_id' => '2A20028071DA23E04188',
                'chat_external_id' => '120363499999999999-group',
                'group_external_id' => '120363499999999999-group',
                'sender_external_id' => '11111111111111@lid',
                'sender_phone' => '554899991111',
                'sender_lid' => '11111111111111@lid',
                'sender_name' => 'Participante Fixture',
                'caption' => 'Teste de grupo',
                'media_url' => 'https://cdn.fixture.test/zapi/group-image-with-caption.jpg',
            ],
        ],
    );

    app(ProcessInboundWebhookJob::class, [
        'provider' => 'zapi',
        'payload' => $payload,
    ])->handle();

    $webhookLog = ChannelWebhookLog::query()->sole();
    $inboundMessage = InboundMessage::query()->sole();
    $eventMedia = EventMedia::query()->sole();

    expect($webhookLog->event_channel_id)->toBe($channel->id)
        ->and($webhookLog->routing_status)->toBe('processed')
        ->and($webhookLog->inbound_message_id)->toBe($inboundMessage->id);

    expect($inboundMessage->event_id)->toBe($event->id)
        ->and($inboundMessage->event_channel_id)->toBe($channel->id)
        ->and($inboundMessage->provider)->toBe('zapi')
        ->and($inboundMessage->message_id)->toBe('2A20028071DA23E04188')
        ->and($inboundMessage->message_type)->toBe('image')
        ->and($inboundMessage->sender_phone)->toBe('554899991111')
        ->and($inboundMessage->sender_name)->toBe('Participante Fixture')
        ->and($inboundMessage->body_text)->toBe('Teste de grupo')
        ->and($inboundMessage->media_url)->toBe('https://cdn.fixture.test/zapi/group-image-with-caption.jpg')
        ->and($inboundMessage->status)->toBe('processed')
        ->and($inboundMessage->processed_at)->not->toBeNull();

    expect($eventMedia->event_id)->toBe($event->id)
        ->and($eventMedia->inbound_message_id)->toBe($inboundMessage->id)
        ->and($eventMedia->source_type)->toBe('whatsapp_group')
        ->and($eventMedia->source_label)->toBe('Participante Fixture')
        ->and($eventMedia->caption)->toBe('Teste de grupo')
        ->and($eventMedia->original_disk)->toBe('public')
        ->and($eventMedia->original_path)->toContain("events/{$event->id}/originals/")
        ->and($eventMedia->processing_status->value)->toBe('downloaded');

    Storage::disk('public')->assertExists($eventMedia->original_path);
    Queue::assertPushed(GenerateMediaVariantsJob::class, fn (GenerateMediaVariantsJob $job) => $job->eventMediaId === $eventMedia->id);
});

it('normalizes video payloads without confusing scalar photo metadata with media type', function () {
    Bus::fake([DownloadInboundMediaJob::class]);

    $event = Event::factory()->active()->create();
    $channel = EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => 'whatsapp_direct',
        'provider' => 'zapi',
        'external_id' => 'FOTOS',
        'label' => 'WhatsApp direto',
        'status' => 'active',
        'config_json' => [],
    ]);

    $payload = [
        'messageId' => '2A2A-VIDEO-001',
        'phone' => '554899900000',
        'photo' => 'https://cdn.fixture.test/zapi/avatar.jpg',
        'video' => [
            'videoUrl' => 'https://cdn.fixture.test/zapi/private-video.mp4',
            'caption' => 'Video privado',
            'mimeType' => 'video/mp4',
        ],
        '_event_context' => [
            'event_id' => $event->id,
            'event_channel_id' => $channel->id,
            'intake_source' => 'whatsapp_direct',
            'provider_message_id' => '2A2A-VIDEO-001',
            'chat_external_id' => '554899900000',
            'sender_external_id' => '554899900000',
            'sender_phone' => '554899900000',
            'sender_name' => 'Participante Video',
            'media_url' => 'https://cdn.fixture.test/zapi/private-video.mp4',
            'caption' => 'Video privado',
        ],
    ];

    $webhookLog = ChannelWebhookLog::query()->create([
        'event_channel_id' => $channel->id,
        'provider' => 'zapi',
        'provider_update_id' => null,
        'message_id' => '2A2A-VIDEO-001',
        'detected_type' => 'video',
        'routing_status' => 'received',
        'payload_json' => $payload,
    ]);

    app(NormalizeInboundMessageJob::class, ['webhookLogId' => $webhookLog->id])->handle();

    $inboundMessage = InboundMessage::query()->sole();

    expect($inboundMessage->message_type)->toBe('video')
        ->and($inboundMessage->media_url)->toBe('https://cdn.fixture.test/zapi/private-video.mp4')
        ->and($inboundMessage->body_text)->toBe('Video privado');

    Bus::assertDispatched(DownloadInboundMediaJob::class, fn (DownloadInboundMediaJob $job) => $job->inboundMessageId === $inboundMessage->id);
});

it('downloads inbound video as video media and skips image-only stages', function () {
    Storage::fake('public');
    Queue::fake([GenerateMediaVariantsJob::class, RunModerationJob::class]);
    Process::fake();

    Http::fake([
        'https://cdn.fixture.test/*' => Http::response(
            'ftypisommp42-video-binary',
            200,
            ['Content-Type' => 'video/mp4'],
        ),
    ]);

    $event = Event::factory()->active()->create([
        'moderation_mode' => 'ai',
    ]);

    $channel = EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => 'whatsapp_direct',
        'provider' => 'zapi',
        'external_id' => 'FOTOS',
        'label' => 'WhatsApp direto',
        'status' => 'active',
        'config_json' => [],
    ]);

    $inboundMessage = InboundMessage::query()->create([
        'event_id' => $event->id,
        'event_channel_id' => $channel->id,
        'provider' => 'zapi',
        'message_id' => '2A2A-VIDEO-002',
        'message_type' => 'photo',
        'sender_phone' => '554899900000',
        'sender_name' => 'Participante Video',
        'body_text' => 'Video privado',
        'media_url' => 'https://cdn.fixture.test/zapi/private-video.mp4',
        'normalized_payload_json' => [
            'video' => [
                'videoUrl' => 'https://cdn.fixture.test/zapi/private-video.mp4',
                'mimeType' => 'video/mp4',
            ],
            'media' => [
                'width' => 1080,
                'height' => 1920,
                'duration' => 23,
                'has_audio' => true,
                'video_codec' => 'h264',
                'audio_codec' => 'aac',
                'bitrate' => 680000,
                'container' => 'mp4',
            ],
            '_event_context' => [
                'event_id' => $event->id,
                'event_channel_id' => $channel->id,
                'intake_source' => 'whatsapp_direct',
            ],
        ],
        'status' => 'normalized',
        'received_at' => now(),
    ]);

    ChannelWebhookLog::query()->create([
        'event_channel_id' => $channel->id,
        'provider' => 'zapi',
        'provider_update_id' => null,
        'message_id' => '2A2A-VIDEO-002',
        'detected_type' => 'video',
        'routing_status' => 'normalized',
        'payload_json' => [],
        'inbound_message_id' => $inboundMessage->id,
    ]);

    app(DownloadInboundMediaJob::class, ['inboundMessageId' => $inboundMessage->id])->handle();

    $eventMedia = EventMedia::query()->sole();

    expect($eventMedia->media_type)->toBe('video')
        ->and($eventMedia->mime_type)->toBe('video/mp4')
        ->and($eventMedia->original_path)->toContain('.mp4')
        ->and($eventMedia->duration_seconds)->toBe(23)
        ->and($eventMedia->width)->toBe(1080)
        ->and($eventMedia->height)->toBe(1920)
        ->and($eventMedia->has_audio)->toBeTrue()
        ->and($eventMedia->video_codec)->toBe('h264')
        ->and($eventMedia->audio_codec)->toBe('aac')
        ->and($eventMedia->bitrate)->toBe(680000)
        ->and($eventMedia->container)->toBe('mp4')
        ->and($eventMedia->processing_status)->toBe(MediaProcessingStatus::Processed)
        ->and($eventMedia->safety_status)->toBe('skipped')
        ->and($eventMedia->vlm_status)->toBe('skipped')
        ->and($eventMedia->face_index_status)->toBe('skipped');

    Storage::disk('public')->assertExists($eventMedia->original_path);
    Queue::assertNotPushed(GenerateMediaVariantsJob::class);
    Queue::assertPushed(RunModerationJob::class, fn (RunModerationJob $job) => $job->eventMediaId === $eventMedia->id);
    Process::assertNothingRan();
});

it('captures inbound audio on the event without creating gallery media', function () {
    Storage::fake('public');
    Queue::fake([GenerateMediaVariantsJob::class, RunModerationJob::class]);

    Http::fake([
        'https://cdn.fixture.test/*' => Http::response(
            'ogg-audio-binary',
            200,
            ['Content-Type' => 'audio/ogg; codecs=opus'],
        ),
    ]);

    $event = Event::factory()->active()->create();

    $channel = EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => 'whatsapp_group',
        'provider' => 'zapi',
        'external_id' => 'GRUPOAUTO',
        'label' => 'WhatsApp grupos',
        'status' => 'active',
        'config_json' => [],
    ]);

    $inboundMessage = InboundMessage::query()->create([
        'event_id' => $event->id,
        'event_channel_id' => $channel->id,
        'provider' => 'zapi',
        'message_id' => '2A2A-AUDIO-001',
        'message_type' => 'audio',
        'sender_phone' => '554899900000',
        'sender_name' => 'Participante Audio',
        'media_url' => 'https://cdn.fixture.test/zapi/private-audio.ogg',
        'capture_target' => 'event_audio',
        'mime_type' => 'audio/ogg; codecs=opus',
        'normalized_payload_json' => [
            'audio' => [
                'audioUrl' => 'https://cdn.fixture.test/zapi/private-audio.ogg',
                'mimeType' => 'audio/ogg; codecs=opus',
                'seconds' => 2,
            ],
            '_event_context' => [
                'event_id' => $event->id,
                'event_channel_id' => $channel->id,
                'intake_source' => 'whatsapp_group',
                'capture_target' => 'event_audio',
            ],
        ],
        'status' => 'normalized',
        'received_at' => now(),
    ]);

    $webhookLog = ChannelWebhookLog::query()->create([
        'event_channel_id' => $channel->id,
        'provider' => 'zapi',
        'provider_update_id' => null,
        'message_id' => '2A2A-AUDIO-001',
        'detected_type' => 'audio',
        'routing_status' => 'normalized',
        'payload_json' => [],
        'inbound_message_id' => $inboundMessage->id,
    ]);

    app(DownloadInboundMediaJob::class, ['inboundMessageId' => $inboundMessage->id])->handle();

    $inboundMessage->refresh();
    $webhookLog->refresh();

    expect($inboundMessage->capture_target)->toBe('event_audio')
        ->and($inboundMessage->status)->toBe('processed')
        ->and($inboundMessage->stored_disk)->toBe('public')
        ->and($inboundMessage->stored_path)->toBe("events/{$event->id}/audio-recordings/2A2A-AUDIO-001.ogg")
        ->and($inboundMessage->client_filename)->toBe('2A2A-AUDIO-001.ogg')
        ->and($inboundMessage->mime_type)->toBe('audio/ogg; codecs=opus')
        ->and($inboundMessage->size_bytes)->toBe(strlen('ogg-audio-binary'))
        ->and($inboundMessage->captured_at)->not->toBeNull()
        ->and($webhookLog->routing_status)->toBe('processed');

    expect(EventMedia::query()->count())->toBe(0);

    Storage::disk('public')->assertExists($inboundMessage->stored_path);
    Queue::assertNotPushed(GenerateMediaVariantsJob::class);
    Queue::assertNotPushed(RunModerationJob::class);
});
