<?php

use App\Modules\Telegram\Clients\BotApi\TelegramBotApiClient;
use Illuminate\Http\Client\Request;
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

it('calls getMe in the configured telegram bot api endpoint', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.telegram.org/bottest-telegram-token/getMe' => Http::response([
            'ok' => true,
            'result' => [
                'id' => 123456789,
                'is_bot' => true,
                'username' => 'eventovivoBot',
            ],
        ], 200),
    ]);

    $response = app(TelegramBotApiClient::class)->getMe();

    expect(data_get($response, 'result.username'))->toBe('eventovivoBot');

    Http::assertSent(function (Request $request) {
        return $request->method() === 'GET'
            && $request->url() === 'https://api.telegram.org/bottest-telegram-token/getMe';
    });
});

it('calls getWebhookInfo in the configured telegram bot api endpoint', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.telegram.org/bottest-telegram-token/getWebhookInfo' => Http::response([
            'ok' => true,
            'result' => [
                'url' => 'https://example.ngrok-free.app/api/v1/webhooks/telegram',
                'pending_update_count' => 0,
                'last_error_message' => '',
            ],
        ], 200),
    ]);

    $response = app(TelegramBotApiClient::class)->getWebhookInfo();

    expect(data_get($response, 'result.pending_update_count'))->toBe(0);

    Http::assertSent(function (Request $request) {
        return $request->method() === 'GET'
            && $request->url() === 'https://api.telegram.org/bottest-telegram-token/getWebhookInfo';
    });
});

it('registers the webhook with explicit allowed updates and the secret token', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.telegram.org/bottest-telegram-token/setWebhook' => Http::response([
            'ok' => true,
            'result' => true,
        ], 200),
    ]);

    $response = app(TelegramBotApiClient::class)->setWebhook(
        url: 'https://example.ngrok-free.app/api/v1/webhooks/telegram',
        allowedUpdates: ['message'],
        dropPendingUpdates: true,
    );

    expect(data_get($response, 'ok'))->toBeTrue();

    Http::assertSent(function (Request $request) {
        $payload = $request->data();

        return $request->method() === 'POST'
            && $request->url() === 'https://api.telegram.org/bottest-telegram-token/setWebhook'
            && ($payload['url'] ?? null) === 'https://example.ngrok-free.app/api/v1/webhooks/telegram'
            && ($payload['secret_token'] ?? null) === 'secret-telegram-webhook'
            && ($payload['allowed_updates'] ?? null) === ['message']
            && ($payload['drop_pending_updates'] ?? null) === true;
    });
});

it('calls getFile and downloads telegram files through the tokenized file endpoint only at request time', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.telegram.org/bottest-telegram-token/getFile' => Http::response([
            'ok' => true,
            'result' => [
                'file_id' => 'PHOTO_BIG',
                'file_unique_id' => 'PHOTO_UNIQUE_BIG',
                'file_path' => 'photos/file_123.jpg',
            ],
        ], 200),
        'https://api.telegram.org/file/bottest-telegram-token/photos/file_123.jpg' => Http::response('binary', 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    $client = app(TelegramBotApiClient::class);
    $file = $client->getFile('PHOTO_BIG');
    $download = $client->downloadFile(data_get($file, 'result.file_path'));

    expect(data_get($file, 'result.file_path'))->toBe('photos/file_123.jpg')
        ->and($download->body())->toBe('binary')
        ->and($download->header('Content-Type'))->toBe('image/jpeg');

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

it('throws when the telegram bot token is not configured', function () {
    config()->set('services.telegram.bot_token', '');

    expect(fn () => app(TelegramBotApiClient::class)->getMe())
        ->toThrow(\InvalidArgumentException::class);
});
