<?php

use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Enums\EventStatus;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventMediaSenderBlacklist;
use App\Modules\Events\Models\EventModule;
use App\Modules\InboundMedia\Jobs\NormalizeInboundMessageJob;
use App\Modules\InboundMedia\Models\ChannelWebhookLog;
use App\Modules\Telegram\Jobs\SendTelegramFeedbackJob;
use App\Modules\Telegram\Models\TelegramInboxSession;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    config()->set('services.telegram.webhook_secret_token', 'secret-telegram-webhook');
});

function makeTelegramPrivateTextUpdateForActivation(string $text, array $overrides = []): array
{
    return array_replace_recursive([
        'update_id' => 900,
        'message' => [
            'message_id' => 70,
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
            'date' => 1775461200,
            'text' => $text,
        ],
    ], $overrides);
}

it('opens a private telegram intake session when the user sends start with a valid event code', function () {
    Bus::fake();

    [$event, $channel] = createTelegramPrivateEventAndChannel();

    $response = $this->apiPost('/webhooks/telegram', makeTelegramPrivateTextUpdateForActivation('/start ANAEJOAO'), [
        'X-Telegram-Bot-Api-Secret-Token' => 'secret-telegram-webhook',
    ]);

    $this->assertApiSuccess($response);

    Bus::assertNotDispatched(NormalizeInboundMessageJob::class);
    Bus::assertDispatched(SendTelegramFeedbackJob::class, function (SendTelegramFeedbackJob $job) use ($event, $channel) {
        return $job->eventId === $event->id
            && $job->phase === 'session_activated'
            && data_get($job->context, 'event_channel_id') === $channel->id
            && data_get($job->context, 'provider_message_id') === '70'
            && data_get($job->context, 'chat_external_id') === '9007199254740991';
    });

    $session = TelegramInboxSession::query()->sole();

    expect($session->organization_id)->toBe($event->organization_id)
        ->and($session->event_id)->toBe($event->id)
        ->and($session->event_channel_id)->toBe($channel->id)
        ->and($session->chat_external_id)->toBe('9007199254740991')
        ->and($session->sender_external_id)->toBe('9007199254740991')
        ->and($session->sender_name)->toBe('Ana')
        ->and($session->status)->toBe('active')
        ->and($session->activated_by_provider_message_id)->toBe('70')
        ->and($session->last_inbound_provider_message_id)->toBe('70')
        ->and(data_get($session->metadata_json, 'media_inbox_code'))->toBe('ANAEJOAO');

    $webhookLog = ChannelWebhookLog::query()->sole();

    expect($webhookLog->provider_update_id)->toBe('900')
        ->and($webhookLog->routing_status)->toBe('session_activated');
});

it('opens a private telegram intake session when the user sends the event code as a standalone message without an active session', function () {
    Bus::fake();

    [$event, $channel] = createTelegramPrivateEventAndChannel();

    $response = $this->apiPost('/webhooks/telegram', makeTelegramPrivateTextUpdateForActivation('ANAEJOAO', [
        'update_id' => 902,
        'message' => [
            'message_id' => 72,
        ],
    ]), [
        'X-Telegram-Bot-Api-Secret-Token' => 'secret-telegram-webhook',
    ]);

    $this->assertApiSuccess($response);

    $session = TelegramInboxSession::query()->sole();

    expect($session->event_id)->toBe($event->id)
        ->and($session->event_channel_id)->toBe($channel->id)
        ->and($session->status)->toBe('active')
        ->and($session->activated_by_provider_message_id)->toBe('72');

    Bus::assertDispatched(SendTelegramFeedbackJob::class, function (SendTelegramFeedbackJob $job) use ($event, $channel) {
        return $job->eventId === $event->id
            && $job->phase === 'session_activated'
            && data_get($job->context, 'event_channel_id') === $channel->id
            && data_get($job->context, 'provider_message_id') === '72';
    });

    $webhookLog = ChannelWebhookLog::query()->sole();

    expect($webhookLog->routing_status)->toBe('session_activated');
});

it('does not open a telegram private session when the start code is invalid', function () {
    Bus::fake();

    createTelegramPrivateEventAndChannel();

    $response = $this->apiPost('/webhooks/telegram', makeTelegramPrivateTextUpdateForActivation('/start CODIGOERRADO'), [
        'X-Telegram-Bot-Api-Secret-Token' => 'secret-telegram-webhook',
    ]);

    $this->assertApiSuccess($response);

    expect(TelegramInboxSession::query()->count())->toBe(0);

    $webhookLog = ChannelWebhookLog::query()->sole();

    expect($webhookLog->routing_status)->toBe('ignored')
        ->and($webhookLog->error_message)->toBe('invalid_activation_code');
});

it('does not open a telegram private session when the sender is blacklisted and dispatches blocked feedback', function () {
    Bus::fake();

    [$event, $channel] = createTelegramPrivateEventAndChannel();
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

    $response = $this->apiPost('/webhooks/telegram', makeTelegramPrivateTextUpdateForActivation('/start ANAEJOAO', [
        'update_id' => 903,
        'message' => [
            'message_id' => 73,
        ],
    ]), [
        'X-Telegram-Bot-Api-Secret-Token' => 'secret-telegram-webhook',
    ]);

    $this->assertApiSuccess($response);

    expect(TelegramInboxSession::query()->count())->toBe(0);

    Bus::assertNotDispatched(NormalizeInboundMessageJob::class);
    Bus::assertDispatched(SendTelegramFeedbackJob::class, function (SendTelegramFeedbackJob $job) use ($event, $channel) {
        return $job->eventId === $event->id
            && $job->phase === 'blocked'
            && data_get($job->context, 'event_channel_id') === $channel->id
            && data_get($job->context, 'provider_message_id') === '73'
            && data_get($job->context, 'chat_external_id') === '9007199254740991';
    });

    $webhookLog = ChannelWebhookLog::query()->sole();

    expect($webhookLog->event_channel_id)->toBe($channel->id)
        ->and($webhookLog->routing_status)->toBe('blocked')
        ->and($webhookLog->error_message)->toBe('sender_blacklisted');
});

it('closes the active telegram private session when the user sends sair', function () {
    Bus::fake();

    [$event, $channel] = createTelegramPrivateEventAndChannel();

    TelegramInboxSession::query()->create([
        'organization_id' => $event->organization_id,
        'event_id' => $event->id,
        'event_channel_id' => $channel->id,
        'chat_external_id' => '9007199254740991',
        'sender_external_id' => '9007199254740991',
        'sender_name' => 'Ana',
        'status' => 'active',
        'activated_by_provider_message_id' => '69',
        'last_inbound_provider_message_id' => '69',
        'activated_at' => now()->subMinute(),
        'last_interaction_at' => now()->subMinute(),
        'expires_at' => now()->addMinutes(30),
    ]);

    $response = $this->apiPost('/webhooks/telegram', makeTelegramPrivateTextUpdateForActivation('SAIR', [
        'update_id' => 901,
        'message' => [
            'message_id' => 71,
        ],
    ]), [
        'X-Telegram-Bot-Api-Secret-Token' => 'secret-telegram-webhook',
    ]);

    $this->assertApiSuccess($response);

    Bus::assertDispatched(SendTelegramFeedbackJob::class, function (SendTelegramFeedbackJob $job) use ($event, $channel) {
        return $job->eventId === $event->id
            && $job->phase === 'session_closed'
            && data_get($job->context, 'event_channel_id') === $channel->id
            && data_get($job->context, 'provider_message_id') === '71';
    });

    $session = TelegramInboxSession::query()->sole();

    expect($session->status)->toBe('closed')
        ->and($session->closed_at)->not->toBeNull()
        ->and($session->last_inbound_provider_message_id)->toBe('71');

    $webhookLog = ChannelWebhookLog::query()->sole();

    expect($webhookLog->routing_status)->toBe('session_closed');
});

function createTelegramPrivateEventAndChannel(): array
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

    return [$event, $channel];
}
