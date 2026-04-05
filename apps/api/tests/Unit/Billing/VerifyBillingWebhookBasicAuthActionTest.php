<?php

use App\Modules\Billing\Actions\VerifyBillingWebhookBasicAuthAction;

it('accepts pagarme basic auth from the authorization header when php auth vars are absent', function () {
    config()->set('services.pagarme.webhook_basic_auth_user', 'eventovivos');
    config()->set('services.pagarme.webhook_basic_auth_password', '!@#159!@#Mudar');

    $result = app(VerifyBillingWebhookBasicAuthAction::class)->execute(
        'pagarme',
        null,
        null,
        [
            'Authorization' => 'Basic '.base64_encode('eventovivos:!@#159!@#Mudar'),
        ],
    );

    expect($result)->toBeTrue();
});

it('accepts pagarme basic auth from the authorization header when php auth vars are blank strings', function () {
    config()->set('services.pagarme.webhook_basic_auth_user', 'eventovivos');
    config()->set('services.pagarme.webhook_basic_auth_password', '!@#159!@#Mudar');

    $result = app(VerifyBillingWebhookBasicAuthAction::class)->execute(
        'pagarme',
        '',
        '',
        [
            'Authorization' => 'Basic '.base64_encode('eventovivos:!@#159!@#Mudar'),
        ],
    );

    expect($result)->toBeTrue();
});

it('rejects pagarme basic auth when the authorization header is invalid and php auth vars are absent', function () {
    config()->set('services.pagarme.webhook_basic_auth_user', 'eventovivos');
    config()->set('services.pagarme.webhook_basic_auth_password', '!@#159!@#Mudar');

    $result = app(VerifyBillingWebhookBasicAuthAction::class)->execute(
        'pagarme',
        null,
        null,
        [
            'authorization' => ['Basic '.base64_encode('invalid:credentials')],
        ],
    );

    expect($result)->toBeFalse();
});

it('accepts pagarme basic auth from server authorization vars when the header bag is empty', function () {
    config()->set('services.pagarme.webhook_basic_auth_user', 'eventovivos');
    config()->set('services.pagarme.webhook_basic_auth_password', '!@#159!@#Mudar');

    $result = app(VerifyBillingWebhookBasicAuthAction::class)->execute(
        'pagarme',
        '',
        '',
        [],
        [
            'HTTP_AUTHORIZATION' => 'Basic '.base64_encode('eventovivos:!@#159!@#Mudar'),
        ],
    );

    expect($result)->toBeTrue();
});
