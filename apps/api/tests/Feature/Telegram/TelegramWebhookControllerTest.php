<?php

use App\Modules\InboundMedia\Jobs\NormalizeInboundMessageJob;
use App\Modules\InboundMedia\Models\ChannelWebhookLog;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\Telegram\Models\TelegramInboxSession;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    config()->set('services.telegram.webhook_secret_token', 'secret-telegram-webhook');
});

function makeTelegramPhotoUpdate(array $overrides = []): array
{
    return array_replace_recursive([
        'update_id' => 124,
        'message' => [
            'message_id' => 81,
            'from' => [
                'id' => 111,
                'is_bot' => false,
                'first_name' => 'Ana',
            ],
            'chat' => [
                'id' => 111,
                'type' => 'private',
            ],
            'date' => 1775461210,
            'photo' => [
                [
                    'file_id' => 'AAA_small',
                    'file_unique_id' => 'U_small',
                    'width' => 90,
                    'height' => 90,
                    'file_size' => 1200,
                ],
                [
                    'file_id' => 'AAA_big',
                    'file_unique_id' => 'U_big',
                    'width' => 1080,
                    'height' => 1350,
                    'file_size' => 245000,
                ],
            ],
            'caption' => 'Evento ao vivo',
        ],
    ], $overrides);
}

it('dispatches private telegram updates only when the configured secret token is valid', function () {
    Bus::fake();

    createTelegramWebhookControllerActiveSession();

    $payload = makeTelegramPhotoUpdate();

    $response = $this->apiPost('/webhooks/telegram', $payload, [
        'X-Telegram-Bot-Api-Secret-Token' => 'secret-telegram-webhook',
    ]);

    $this->assertApiSuccess($response);

    Bus::assertDispatched(NormalizeInboundMessageJob::class);

    $webhookLog = ChannelWebhookLog::query()->sole();

    expect($webhookLog->provider)->toBe('telegram')
        ->and($webhookLog->provider_update_id)->toBe('124')
        ->and($webhookLog->message_id)->toBe('81')
        ->and($webhookLog->detected_type)->toBe('photo')
        ->and($webhookLog->routing_status)->toBe('received');
});

it('rejects telegram webhook requests when the secret token is invalid', function () {
    Bus::fake();

    $response = $this->apiPost('/webhooks/telegram', makeTelegramPhotoUpdate(), [
        'X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'Invalid Telegram webhook secret.',
        ]);

    Bus::assertNotDispatched(NormalizeInboundMessageJob::class);

    expect(ChannelWebhookLog::query()->count())->toBe(0);
});

it('deduplicates telegram webhook retries by update_id before dispatching work', function () {
    Bus::fake();

    createTelegramWebhookControllerActiveSession();

    $payload = makeTelegramPhotoUpdate([
        'update_id' => 777,
        'message' => [
            'message_id' => 90,
        ],
    ]);

    $headers = ['X-Telegram-Bot-Api-Secret-Token' => 'secret-telegram-webhook'];

    $firstResponse = $this->apiPost('/webhooks/telegram', $payload, $headers);
    $secondResponse = $this->apiPost('/webhooks/telegram', $payload, $headers);

    $this->assertApiSuccess($firstResponse);
    $this->assertApiSuccess($secondResponse);

    expect(ChannelWebhookLog::query()->count())->toBe(1)
        ->and(ChannelWebhookLog::query()->sole()->provider_update_id)->toBe('777');

    Bus::assertDispatchedTimes(NormalizeInboundMessageJob::class, 1);
});

function createTelegramWebhookControllerActiveSession(): void
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

    TelegramInboxSession::query()->create([
        'organization_id' => $event->organization_id,
        'event_id' => $event->id,
        'event_channel_id' => $channel->id,
        'chat_external_id' => '111',
        'sender_external_id' => '111',
        'sender_name' => 'Ana',
        'status' => 'active',
        'activated_by_provider_message_id' => '80',
        'last_inbound_provider_message_id' => '80',
        'activated_at' => now()->subMinute(),
        'last_interaction_at' => now()->subMinute(),
        'expires_at' => now()->addMinutes(180),
        'metadata_json' => [
            'media_inbox_code' => 'ANAEJOAO',
        ],
    ]);
}

it('ignores non private telegram updates and stores a technical log entry', function () {
    Bus::fake();

    $payload = makeTelegramPhotoUpdate([
        'message' => [
            'chat' => [
                'id' => -1001234567890,
                'type' => 'group',
            ],
        ],
    ]);

    $response = $this->apiPost('/webhooks/telegram', $payload, [
        'X-Telegram-Bot-Api-Secret-Token' => 'secret-telegram-webhook',
    ]);

    $this->assertApiSuccess($response);

    Bus::assertNotDispatched(NormalizeInboundMessageJob::class);

    $webhookLog = ChannelWebhookLog::query()->sole();

    expect($webhookLog->provider)->toBe('telegram')
        ->and($webhookLog->provider_update_id)->toBe('124')
        ->and($webhookLog->message_id)->toBe('81')
        ->and($webhookLog->routing_status)->toBe('ignored')
        ->and($webhookLog->error_message)->toBe('out_of_scope_chat_type');
});

it('stores private my_chat_member updates as operational signals without dispatching media normalization', function () {
    Bus::fake();

    createTelegramWebhookControllerActiveSession();

    $payload = [
        'update_id' => 991,
        'my_chat_member' => [
            'chat' => [
                'id' => 111,
                'type' => 'private',
            ],
            'from' => [
                'id' => 111,
                'is_bot' => false,
                'first_name' => 'Ana',
            ],
            'date' => 1775461400,
            'old_chat_member' => [
                'status' => 'member',
            ],
            'new_chat_member' => [
                'status' => 'kicked',
            ],
        ],
    ];

    $response = $this->apiPost('/webhooks/telegram', $payload, [
        'X-Telegram-Bot-Api-Secret-Token' => 'secret-telegram-webhook',
    ]);

    $this->assertApiSuccess($response);

    Bus::assertNotDispatched(NormalizeInboundMessageJob::class);

    $webhookLog = ChannelWebhookLog::query()->sole();

    expect($webhookLog->event_channel_id)->not->toBeNull()
        ->and($webhookLog->provider)->toBe('telegram')
        ->and($webhookLog->provider_update_id)->toBe('991')
        ->and($webhookLog->message_id)->toBeNull()
        ->and($webhookLog->detected_type)->toBe('my_chat_member')
        ->and($webhookLog->routing_status)->toBe('operational_signal')
        ->and($webhookLog->error_message)->toBe('bot_blocked_by_user');
});

it('ignores raw telegram updates in the current normalizer because the webhook does not carry event context yet', function () {
    $webhookLog = ChannelWebhookLog::query()->create([
        'provider' => 'telegram',
        'message_id' => null,
        'detected_type' => 'unknown',
        'routing_status' => 'received',
        'payload_json' => makeTelegramPhotoUpdate(),
    ]);

    app(NormalizeInboundMessageJob::class, [
        'webhookLogId' => $webhookLog->id,
    ])->handle();

    $webhookLog->refresh();

    expect($webhookLog->routing_status)->toBe('ignored')
        ->and($webhookLog->error_message)->toBe('missing_event_context')
        ->and(InboundMessage::query()->count())->toBe(0);
});
