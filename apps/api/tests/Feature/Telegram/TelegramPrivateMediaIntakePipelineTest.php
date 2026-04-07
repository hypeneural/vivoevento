<?php

use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventMediaSenderBlacklist;
use App\Modules\Events\Models\EventModule;
use App\Modules\InboundMedia\Jobs\NormalizeInboundMessageJob;
use App\Modules\InboundMedia\Models\ChannelWebhookLog;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Jobs\DownloadInboundMediaJob;
use App\Modules\MediaProcessing\Jobs\GenerateMediaVariantsJob;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Telegram\Jobs\SendTelegramFeedbackJob;
use App\Modules\Telegram\Models\TelegramInboxSession;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('services.telegram', [
        'base_url' => 'https://api.telegram.org',
        'bot_token' => 'test-telegram-token',
        'webhook_secret_token' => 'secret-telegram-webhook',
        'timeout' => 15,
        'connect_timeout' => 5,
    ]);
});

it('normalizes telegram private photo updates through the active session without persisting tokenized media urls', function () {
    Bus::fake();

    [$event, $channel, $session] = createTelegramPrivateEventChannelAndSessionForMedia();

    $response = $this->apiPost('/webhooks/telegram', makeTelegramPrivateMediaUpdateForPipeline('photo'), [
        'X-Telegram-Bot-Api-Secret-Token' => 'secret-telegram-webhook',
    ]);

    $this->assertApiSuccess($response);

    Bus::assertDispatched(NormalizeInboundMessageJob::class);

    $webhookLog = ChannelWebhookLog::query()->sole();
    $payload = $webhookLog->payload_json;

    expect($webhookLog->event_channel_id)->toBe($channel->id)
        ->and($webhookLog->routing_status)->toBe('received')
        ->and(data_get($payload, '_event_context.event_id'))->toBe($event->id)
        ->and(data_get($payload, '_event_context.event_channel_id'))->toBe($channel->id)
        ->and(data_get($payload, '_event_context.inbox_session_id'))->toBe($session->id)
        ->and(data_get($payload, '_event_context.intake_source'))->toBe('telegram')
        ->and(data_get($payload, '_event_context.source_subtype'))->toBe('direct')
        ->and(data_get($payload, '_event_context.provider_update_id'))->toBe('1000')
        ->and(data_get($payload, '_event_context.provider_message_id'))->toBe('81')
        ->and(data_get($payload, 'message_type'))->toBe('photo')
        ->and(data_get($payload, 'media.download_strategy'))->toBe('telegram_file')
        ->and(data_get($payload, 'media.file_id'))->toBe('PHOTO_BIG')
        ->and(data_get($payload, 'media.file_unique_id'))->toBe('PHOTO_UNIQUE_BIG')
        ->and(data_get($payload, 'media.width'))->toBe(1080)
        ->and(data_get($payload, 'media.height'))->toBe(1350)
        ->and(data_get($payload, '_event_context.media_url'))->toBeNull()
        ->and((string) json_encode($payload))->not->toContain('test-telegram-token');

    app(NormalizeInboundMessageJob::class, ['webhookLogId' => $webhookLog->id])->handle();

    $inboundMessage = InboundMessage::query()->sole();

    expect($inboundMessage->event_id)->toBe($event->id)
        ->and($inboundMessage->event_channel_id)->toBe($channel->id)
        ->and($inboundMessage->provider)->toBe('telegram')
        ->and($inboundMessage->message_id)->toBe('81')
        ->and($inboundMessage->message_type)->toBe('photo')
        ->and($inboundMessage->chat_external_id)->toBe('9007199254740991')
        ->and($inboundMessage->sender_external_id)->toBe('9007199254740991')
        ->and($inboundMessage->sender_name)->toBe('Ana')
        ->and($inboundMessage->body_text)->toBe('Evento ao vivo')
        ->and($inboundMessage->media_url)->toBeNull()
        ->and(data_get($inboundMessage->normalized_payload_json, 'media.file_id'))->toBe('PHOTO_BIG')
        ->and(data_get($inboundMessage->normalized_payload_json, 'media.download_strategy'))->toBe('telegram_file')
        ->and((string) json_encode($inboundMessage->normalized_payload_json))->not->toContain('test-telegram-token');

    Bus::assertDispatched(DownloadInboundMediaJob::class);
    Bus::assertDispatched(SendTelegramFeedbackJob::class, function (SendTelegramFeedbackJob $job) use ($event, $channel) {
        return $job->eventId === $event->id
            && $job->phase === 'detected'
            && data_get($job->context, 'event_channel_id') === $channel->id
            && data_get($job->context, 'provider_message_id') === '81'
            && data_get($job->context, 'chat_external_id') === '9007199254740991';
    });
});

it('normalizes telegram private video and document updates with official message types', function (string $messageType, string $expectedFileId, ?string $expectedMimeType) {
    Bus::fake();

    [$event, $channel] = createTelegramPrivateEventChannelAndSessionForMedia();

    $response = $this->apiPost('/webhooks/telegram', makeTelegramPrivateMediaUpdateForPipeline($messageType, [
        'update_id' => $messageType === 'video' ? 1001 : 1002,
        'message' => [
            'message_id' => $messageType === 'video' ? 82 : 83,
        ],
    ]), [
        'X-Telegram-Bot-Api-Secret-Token' => 'secret-telegram-webhook',
    ]);

    $this->assertApiSuccess($response);

    $webhookLog = ChannelWebhookLog::query()->sole();

    app(NormalizeInboundMessageJob::class, ['webhookLogId' => $webhookLog->id])->handle();

    $inboundMessage = InboundMessage::query()->sole();

    expect($inboundMessage->event_id)->toBe($event->id)
        ->and($inboundMessage->event_channel_id)->toBe($channel->id)
        ->and($inboundMessage->message_type)->toBe($messageType)
        ->and(data_get($inboundMessage->normalized_payload_json, 'media.file_id'))->toBe($expectedFileId)
        ->and(data_get($inboundMessage->normalized_payload_json, 'media.mime_type'))->toBe($expectedMimeType);

    Bus::assertDispatched(DownloadInboundMediaJob::class);
})->with([
    'video' => ['video', 'VIDEO_001', 'video/mp4'],
    'document' => ['document', 'DOC_001', 'application/pdf'],
]);

it('ignores telegram private media when there is no active session for the chat', function () {
    Bus::fake();

    createTelegramPrivateEventChannelAndSessionForMedia(chatId: '222222222');

    $response = $this->apiPost('/webhooks/telegram', makeTelegramPrivateMediaUpdateForPipeline('photo'), [
        'X-Telegram-Bot-Api-Secret-Token' => 'secret-telegram-webhook',
    ]);

    $this->assertApiSuccess($response);

    Bus::assertNotDispatched(NormalizeInboundMessageJob::class);
    Bus::assertNotDispatched(SendTelegramFeedbackJob::class);

    $webhookLog = ChannelWebhookLog::query()->sole();

    expect($webhookLog->routing_status)->toBe('ignored')
        ->and($webhookLog->error_message)->toBe('no_active_session')
        ->and(InboundMessage::query()->count())->toBe(0);
});

it('blocks telegram private media from blacklisted senders before canonical normalization', function () {
    Bus::fake();

    [$event, $channel, $session] = createTelegramPrivateEventChannelAndSessionForMedia();
    $event->update([
        'current_entitlements_json' => [
            'channels' => [
                'telegram' => ['enabled' => true],
                'blacklist' => ['enabled' => true],
            ],
        ],
    ]);

    EventMediaSenderBlacklist::factory()->create([
        'event_id' => $event->id,
        'identity_type' => 'external_id',
        'identity_value' => '9007199254740991',
        'normalized_phone' => null,
        'reason' => 'Bloqueado no Telegram para testes',
        'is_active' => true,
    ]);

    $response = $this->apiPost('/webhooks/telegram', makeTelegramPrivateMediaUpdateForPipeline('photo', [
        'update_id' => 1003,
        'message' => [
            'message_id' => 84,
        ],
    ]), [
        'X-Telegram-Bot-Api-Secret-Token' => 'secret-telegram-webhook',
    ]);

    $this->assertApiSuccess($response);

    Bus::assertNotDispatched(NormalizeInboundMessageJob::class);
    Bus::assertDispatched(SendTelegramFeedbackJob::class, function (SendTelegramFeedbackJob $job) use ($event, $channel) {
        return $job->eventId === $event->id
            && $job->phase === 'blocked'
            && data_get($job->context, 'event_channel_id') === $channel->id
            && data_get($job->context, 'provider_message_id') === '84'
            && data_get($job->context, 'chat_external_id') === '9007199254740991';
    });

    $webhookLog = ChannelWebhookLog::query()->sole();

    expect($webhookLog->event_channel_id)->toBe($channel->id)
        ->and($webhookLog->routing_status)->toBe('blocked')
        ->and($webhookLog->error_message)->toBe('sender_blacklisted')
        ->and($session->fresh()->status)->toBe('active')
        ->and(InboundMessage::query()->count())->toBe(0);
});

it('downloads telegram files through getFile without persisting a tokenized download url', function () {
    Storage::fake('public');
    Queue::fake([GenerateMediaVariantsJob::class]);
    Http::preventStrayRequests();

    Http::fake([
        'https://api.telegram.org/bottest-telegram-token/getFile' => Http::response([
            'ok' => true,
            'result' => [
                'file_id' => 'PHOTO_BIG',
                'file_unique_id' => 'PHOTO_UNIQUE_BIG',
                'file_size' => 245000,
                'file_path' => 'photos/file_123.jpg',
            ],
        ], 200),
        'https://api.telegram.org/file/bottest-telegram-token/photos/file_123.jpg' => Http::response('telegram-photo-binary', 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    [$event, $channel] = createTelegramPrivateEventChannelAndSessionForMedia();

    $inboundMessage = InboundMessage::query()->create([
        'event_id' => $event->id,
        'event_channel_id' => $channel->id,
        'provider' => 'telegram',
        'message_id' => '81',
        'message_type' => 'photo',
        'chat_external_id' => '9007199254740991',
        'sender_external_id' => '9007199254740991',
        'sender_name' => 'Ana',
        'body_text' => 'Evento ao vivo',
        'media_url' => null,
        'normalized_payload_json' => [
            'message_type' => 'photo',
            'media' => [
                'download_strategy' => 'telegram_file',
                'file_id' => 'PHOTO_BIG',
                'file_unique_id' => 'PHOTO_UNIQUE_BIG',
                'mime_type' => null,
            ],
            '_event_context' => [
                'event_id' => $event->id,
                'event_channel_id' => $channel->id,
                'intake_source' => 'telegram',
                'source_subtype' => 'direct',
            ],
        ],
        'status' => 'normalized',
        'received_at' => now(),
    ]);

    ChannelWebhookLog::query()->create([
        'event_channel_id' => $channel->id,
        'provider' => 'telegram',
        'provider_update_id' => '1000',
        'message_id' => '81',
        'detected_type' => 'photo',
        'routing_status' => 'normalized',
        'payload_json' => [],
        'inbound_message_id' => $inboundMessage->id,
    ]);

    app(DownloadInboundMediaJob::class, ['inboundMessageId' => $inboundMessage->id])->handle();

    $eventMedia = EventMedia::query()->sole();
    $inboundMessage->refresh();

    expect($eventMedia->event_id)->toBe($event->id)
        ->and($eventMedia->inbound_message_id)->toBe($inboundMessage->id)
        ->and($eventMedia->source_type)->toBe('telegram')
        ->and($eventMedia->source_label)->toBe('Ana')
        ->and($eventMedia->caption)->toBe('Evento ao vivo')
        ->and($eventMedia->mime_type)->toBe('image/jpeg')
        ->and($eventMedia->size_bytes)->toBe(strlen('telegram-photo-binary'))
        ->and($eventMedia->original_path)->toContain("events/{$event->id}/originals/81.jpg")
        ->and($inboundMessage->media_url)->toBeNull()
        ->and((string) json_encode($inboundMessage->normalized_payload_json))->not->toContain('test-telegram-token')
        ->and(data_get($inboundMessage->normalized_payload_json, 'media.file_path'))->toBe('photos/file_123.jpg');

    Storage::disk('public')->assertExists($eventMedia->original_path);
    Queue::assertPushed(GenerateMediaVariantsJob::class, fn (GenerateMediaVariantsJob $job) => $job->eventMediaId === $eventMedia->id);

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.telegram.org/bottest-telegram-token/getFile'
            && ($request->data()['file_id'] ?? null) === 'PHOTO_BIG';
    });

    Http::assertSent(function (Request $request) {
        return $request->method() === 'GET'
            && $request->url() === 'https://api.telegram.org/file/bottest-telegram-token/photos/file_123.jpg';
    });
});

function createTelegramPrivateEventChannelAndSessionForMedia(string $chatId = '9007199254740991'): array
{
    $event = Event::factory()->active()->create([
        'current_entitlements_json' => [
            'channels' => [
                'telegram' => [
                    'enabled' => true,
                ],
            ],
        ],
    ]);

    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);

    $channel = EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::TelegramBot->value,
        'provider' => 'telegram',
        'external_id' => 'ANAEJOAO',
        'label' => 'Telegram',
        'status' => 'active',
        'config_json' => [
            'bot_username' => 'eventovivoBot',
            'media_inbox_code' => 'ANAEJOAO',
            'session_ttl_minutes' => 180,
            'allow_private' => true,
            'v1_allowed_updates' => ['message'],
        ],
    ]);

    $session = TelegramInboxSession::query()->create([
        'organization_id' => $event->organization_id,
        'event_id' => $event->id,
        'event_channel_id' => $channel->id,
        'chat_external_id' => $chatId,
        'sender_external_id' => $chatId,
        'sender_name' => 'Ana',
        'status' => 'active',
        'activated_by_provider_message_id' => '70',
        'last_inbound_provider_message_id' => '70',
        'activated_at' => now()->subMinute(),
        'last_interaction_at' => now()->subMinute(),
        'expires_at' => now()->addMinutes(180),
        'metadata_json' => [
            'media_inbox_code' => 'ANAEJOAO',
        ],
    ]);

    return [$event, $channel, $session];
}

function makeTelegramPrivateMediaUpdateForPipeline(string $messageType, array $overrides = []): array
{
    $mediaPayload = match ($messageType) {
        'video' => [
            'video' => [
                'file_id' => 'VIDEO_001',
                'file_unique_id' => 'VIDEO_UNIQUE_001',
                'width' => 1280,
                'height' => 720,
                'duration' => 12,
                'mime_type' => 'video/mp4',
                'file_name' => 'palco.mp4',
                'file_size' => 1820000,
            ],
            'caption' => 'Palco lotado',
        ],
        'document' => [
            'document' => [
                'file_id' => 'DOC_001',
                'file_unique_id' => 'DOC_UNIQUE_001',
                'mime_type' => 'application/pdf',
                'file_name' => 'programacao.pdf',
                'file_size' => 120000,
            ],
            'caption' => 'Programacao',
        ],
        default => [
            'photo' => [
                [
                    'file_id' => 'PHOTO_SMALL',
                    'file_unique_id' => 'PHOTO_UNIQUE_SMALL',
                    'width' => 90,
                    'height' => 90,
                    'file_size' => 1200,
                ],
                [
                    'file_id' => 'PHOTO_BIG',
                    'file_unique_id' => 'PHOTO_UNIQUE_BIG',
                    'width' => 1080,
                    'height' => 1350,
                    'file_size' => 245000,
                ],
            ],
            'caption' => 'Evento ao vivo',
        ],
    };

    return array_replace_recursive([
        'update_id' => 1000,
        'message' => array_merge([
            'message_id' => 81,
            'from' => [
                'id' => 9007199254740991,
                'is_bot' => false,
                'first_name' => 'Ana',
            ],
            'chat' => [
                'id' => 9007199254740991,
                'type' => 'private',
                'first_name' => 'Ana',
            ],
            'date' => 1775461210,
        ], $mediaPayload),
    ], $overrides);
}
