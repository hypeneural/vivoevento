<?php

use App\Modules\Billing\Enums\BillingOrderMode;
use App\Modules\Billing\Services\BillingGatewayManager;

it('resolves pagarme as the configured provider for event package orders', function () {
    config()->set('billing.gateways.default', 'manual');
    config()->set('billing.gateways.event_package', 'pagarme');
    config()->set('billing.gateways.providers.pagarme', 'App\\Modules\\Billing\\Services\\Pagarme\\PagarmeBillingGateway');
    config()->set('services.pagarme', [
        'base_url' => 'https://api.pagar.me/core/v5/',
        'secret_key' => 'sk_test_example',
        'public_key' => 'pk_test_example',
        'statement_descriptor' => 'EVENTOVIVO',
        'pix_expires_in' => 1800,
        'timeout' => 15,
        'connect_timeout' => 5,
    ]);

    $gateway = app(BillingGatewayManager::class)->forMode(BillingOrderMode::EventPackage);

    expect($gateway->providerKey())->toBe('pagarme');
});
