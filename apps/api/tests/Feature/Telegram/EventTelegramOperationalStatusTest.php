<?php

use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\InboundMedia\Models\ChannelWebhookLog;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.telegram', [
        'base_url' => 'https://api.telegram.org',
        'bot_token' => 'test-telegram-token',
        'webhook_secret_token' => 'secret-telegram-webhook',
        'timeout' => 15,
        'connect_timeout' => 5,
    ]);
});

it('returns telegram operational status for the event editor with webhook health and recent my_chat_member signals', function () {
    [$user, $organization] = $this->actingAsOwner();

    Http::preventStrayRequests();
    Http::fake([
        'https://api.telegram.org/bottest-telegram-token/getMe' => Http::response([
            'ok' => true,
            'result' => [
                'id' => 123456789,
                'is_bot' => true,
                'username' => 'eventovivoBot',
                'can_join_groups' => true,
                'can_read_all_group_messages' => false,
            ],
        ], 200),
        'https://api.telegram.org/bottest-telegram-token/getWebhookInfo' => Http::response([
            'ok' => true,
            'result' => [
                'url' => 'https://webhooks-local.eventovivo.com.br/api/v1/webhooks/telegram',
                'pending_update_count' => 2,
                'allowed_updates' => ['message', 'my_chat_member'],
                'last_error_message' => '',
                'max_connections' => 40,
            ],
        ], 200),
    ]);

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
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
        'external_id' => 'TGTEST406',
        'label' => 'Telegram',
        'status' => 'active',
        'config_json' => [
            'bot_username' => 'eventovivoBot',
            'media_inbox_code' => 'TGTEST406',
            'session_ttl_minutes' => 180,
            'allow_private' => true,
            'v1_allowed_updates' => ['message', 'my_chat_member'],
        ],
    ]);

    ChannelWebhookLog::query()->create([
        'event_channel_id' => $channel->id,
        'provider' => 'telegram',
        'provider_update_id' => '99001',
        'message_id' => null,
        'detected_type' => 'my_chat_member',
        'routing_status' => 'operational_signal',
        'error_message' => 'bot_blocked_by_user',
        'payload_json' => [
            'update_id' => 99001,
            'my_chat_member' => [
                'chat' => [
                    'id' => 9007199254740991,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 9007199254740991,
                    'is_bot' => false,
                    'first_name' => 'Ana',
                ],
                'date' => 1775461300,
                'old_chat_member' => [
                    'status' => 'member',
                ],
                'new_chat_member' => [
                    'status' => 'kicked',
                ],
            ],
        ],
    ]);

    $response = $this->apiGet("/events/{$event->id}/telegram/operational-status");

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.configured', true)
        ->assertJsonPath('data.healthy', true)
        ->assertJsonPath('data.channel.id', $channel->id)
        ->assertJsonPath('data.channel.bot_username', 'eventovivoBot')
        ->assertJsonPath('data.webhook.allowed_updates.0', 'message')
        ->assertJsonPath('data.webhook.allowed_updates.1', 'my_chat_member')
        ->assertJsonPath('data.webhook.matches_expected_allowed_updates', true)
        ->assertJsonPath('data.recent_operational_signals.0.signal', 'bot_blocked_by_user')
        ->assertJsonPath('data.recent_operational_signals.0.old_status', 'member')
        ->assertJsonPath('data.recent_operational_signals.0.new_status', 'kicked');
});
