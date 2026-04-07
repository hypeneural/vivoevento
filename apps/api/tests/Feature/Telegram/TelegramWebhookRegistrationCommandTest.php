<?php

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

it('registers the telegram webhook in a controlled way with explicit allowed updates and optional drop pending updates', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.telegram.org/bottest-telegram-token/setWebhook' => Http::response([
            'ok' => true,
            'result' => true,
        ], 200),
    ]);

    $this->artisan('telegram:webhook:set', [
        'url' => 'https://eventovivo.example.com/api/v1/webhooks/telegram',
        '--drop-pending' => true,
    ])->assertSuccessful();

    Http::assertSent(function (Request $request) {
        $payload = $request->data();

        return $request->method() === 'POST'
            && $request->url() === 'https://api.telegram.org/bottest-telegram-token/setWebhook'
            && ($payload['url'] ?? null) === 'https://eventovivo.example.com/api/v1/webhooks/telegram'
            && ($payload['allowed_updates'] ?? null) === ['message', 'my_chat_member']
            && ($payload['drop_pending_updates'] ?? null) === true
            && ($payload['secret_token'] ?? null) === 'secret-telegram-webhook';
    });
});

it('rejects telegram webhook registration when the public url is not https', function () {
    Http::preventStrayRequests();
    Http::fake();

    $this->artisan('telegram:webhook:set', [
        'url' => 'http://localhost/api/v1/webhooks/telegram',
    ])->assertFailed();

    Http::assertNothingSent();
});
