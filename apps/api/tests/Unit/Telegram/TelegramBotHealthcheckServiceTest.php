<?php

use App\Modules\Telegram\Services\TelegramBotHealthcheckService;
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

it('builds a private bot healthcheck snapshot from getMe and getWebhookInfo', function () {
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
                'url' => 'https://example.ngrok-free.app/api/v1/webhooks/telegram',
                'pending_update_count' => 0,
                'has_custom_certificate' => false,
                'ip_address' => '149.154.167.220',
                'max_connections' => 40,
                'allowed_updates' => ['message', 'my_chat_member'],
                'last_error_message' => '',
            ],
        ], 200),
    ]);

    $snapshot = app(TelegramBotHealthcheckService::class)->inspect();

    expect(data_get($snapshot, 'bot.ok'))->toBeTrue()
        ->and(data_get($snapshot, 'bot.username'))->toBe('eventovivoBot')
        ->and(data_get($snapshot, 'bot.can_join_groups'))->toBeTrue()
        ->and(data_get($snapshot, 'bot.can_read_all_group_messages'))->toBeFalse()
        ->and(data_get($snapshot, 'webhook.ok'))->toBeTrue()
        ->and(data_get($snapshot, 'webhook.url'))->toBe('https://example.ngrok-free.app/api/v1/webhooks/telegram')
        ->and(data_get($snapshot, 'webhook.pending_update_count'))->toBe(0)
        ->and(data_get($snapshot, 'webhook.allowed_updates'))->toBe(['message', 'my_chat_member'])
        ->and(data_get($snapshot, 'webhook.last_error_message'))->toBeNull();
});

it('builds a degraded operational snapshot when telegram bot configuration is missing', function () {
    config()->set('services.telegram.bot_token', '');

    $snapshot = app(TelegramBotHealthcheckService::class)->inspectSafely();

    expect(data_get($snapshot, 'configured'))->toBeFalse()
        ->and(data_get($snapshot, 'healthy'))->toBeFalse()
        ->and(data_get($snapshot, 'error_message'))->toContain('services.telegram.bot_token')
        ->and(data_get($snapshot, 'bot.ok'))->toBeFalse()
        ->and(data_get($snapshot, 'webhook.ok'))->toBeFalse();
});
