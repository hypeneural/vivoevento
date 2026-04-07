<?php

use App\Modules\Telegram\Support\TelegramWebhookSecretValidator;

beforeEach(function () {
    config()->set('services.telegram.webhook_secret_token', 'secret-telegram-webhook');
});

it('accepts the webhook request when the provided secret matches the configured one', function () {
    $validator = app(TelegramWebhookSecretValidator::class);

    expect($validator->isValid('secret-telegram-webhook'))->toBeTrue();
});

it('rejects the webhook request when the provided secret does not match the configured one', function () {
    $validator = app(TelegramWebhookSecretValidator::class);

    expect($validator->isValid('wrong-secret'))->toBeFalse()
        ->and($validator->isValid(null))->toBeFalse();
});
