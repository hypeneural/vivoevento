<?php

use App\Modules\Billing\Enums\EventPackageAudience;
use App\Modules\Billing\Enums\EventPackageBillingMode;
use App\Modules\Billing\Models\EventPackage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seedPermissions();

    config()->set('billing.gateways.default', 'manual');
    config()->set('billing.gateways.subscription', 'manual');
    config()->set('billing.gateways.event_package', 'manual');
    config()->set('billing.payment_notifications.enabled', false);
    Carbon::setTestNow();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('returns a semantic pending Pix summary for the public checkout payload', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-04T19:30:00Z'));
    configurePayloadContractPagarme();

    Http::preventStrayRequests();
    Http::fake([
        'https://api.pagar.me/core/v5/orders' => Http::response([
            'id' => 'or_contract_pix_123',
            'code' => 'gateway-order-contract-pix-123',
            'status' => 'pending',
            'charges' => [
                [
                    'id' => 'ch_contract_pix_123',
                    'status' => 'pending',
                    'payment_method' => 'pix',
                    'last_transaction' => [
                        'id' => 'pix_tx_contract_123',
                        'qr_code' => '00020101021226890014br.gov.bcb.pix2567pix.example/qr/contract123',
                        'qr_code_url' => 'https://pagar.me/qr/ch_contract_pix_123.png',
                        'expires_at' => '2026-04-04T20:00:00Z',
                    ],
                ],
            ],
        ], 200),
    ]);

    $package = createPayloadContractPublicEventPackage([
        'target_audience' => EventPackageAudience::DirectCustomer->value,
        'amount_cents' => 19900,
    ]);

    $createResponse = $this->apiPost('/public/event-checkouts', [
        'responsible_name' => 'Camila Rocha',
        'whatsapp' => '(48) 99977-1111',
        'email' => 'camila@example.com',
        'package_id' => $package->id,
        'event' => [
            'title' => 'Casamento Camila & Bruno',
            'event_type' => 'wedding',
        ],
        'payment' => [
            'method' => 'pix',
            'pix' => [
                'expires_in' => 1800,
            ],
        ],
    ]);

    $this->assertApiSuccess($createResponse, 201);

    $createResponse->assertJsonPath('data.checkout.summary.state', 'pending');
    $createResponse->assertJsonPath('data.checkout.summary.tone', 'info');
    $createResponse->assertJsonPath('data.checkout.summary.payment_status_title', 'Pix gerado com sucesso');
    $createResponse->assertJsonPath('data.checkout.summary.order_status_label', 'Pedido criado');
    $createResponse->assertJsonPath('data.checkout.summary.payment_status_label', 'Aguardando pagamento');
    $createResponse->assertJsonPath('data.checkout.summary.next_action', 'complete_payment');
    $createResponse->assertJsonPath('data.checkout.summary.expires_in_seconds', 1800);
    $createResponse->assertJsonPath('data.checkout.summary.is_waiting_payment', true);
    $createResponse->assertJsonPath('data.checkout.summary.can_retry', false);

    expect((string) $createResponse->json('data.checkout.summary.payment_status_description'))
        ->toContain('QR Code')
        ->not->toContain('gateway');

    $checkoutUuid = $createResponse->json('data.checkout.uuid');

    $statusResponse = $this->apiGet("/public/event-checkouts/{$checkoutUuid}");

    $this->assertApiSuccess($statusResponse);
    $statusResponse->assertJsonPath('data.checkout.summary.state', 'pending');
    $statusResponse->assertJsonPath('data.checkout.summary.payment_status_label', 'Aguardando pagamento');
    $statusResponse->assertJsonPath('data.checkout.summary.next_action', 'complete_payment');
    $statusResponse->assertJsonPath('data.checkout.summary.is_waiting_payment', true);
});

it('returns a semantic paid summary after the public checkout is confirmed', function () {
    $package = createPayloadContractPublicEventPackage([
        'target_audience' => EventPackageAudience::Both->value,
        'amount_cents' => 29900,
    ]);

    $createResponse = $this->apiPost('/public/event-checkouts', [
        'responsible_name' => 'Mariana Alves',
        'whatsapp' => '(48) 99988-1111',
        'email' => 'mariana@example.com',
        'package_id' => $package->id,
        'event' => [
            'title' => 'Casamento Mariana & Rafael',
            'event_type' => 'wedding',
        ],
    ]);

    $this->assertApiSuccess($createResponse, 201);

    $checkoutUuid = $createResponse->json('data.checkout.uuid');

    $confirmResponse = $this->apiPost("/public/event-checkouts/{$checkoutUuid}/confirm", [
        'gateway_provider' => 'manual_test',
        'gateway_order_id' => 'order_contract_paid_123',
    ]);

    $this->assertApiSuccess($confirmResponse);

    $confirmResponse->assertJsonPath('data.checkout.summary.state', 'paid');
    $confirmResponse->assertJsonPath('data.checkout.summary.tone', 'success');
    $confirmResponse->assertJsonPath('data.checkout.summary.payment_status_title', 'Pagamento confirmado');
    $confirmResponse->assertJsonPath('data.checkout.summary.order_status_label', 'Pedido confirmado');
    $confirmResponse->assertJsonPath('data.checkout.summary.payment_status_label', 'Confirmado');
    $confirmResponse->assertJsonPath('data.checkout.summary.next_action', 'open_event');
    $confirmResponse->assertJsonPath('data.checkout.summary.is_waiting_payment', false);
    $confirmResponse->assertJsonPath('data.checkout.summary.can_retry', false);

    expect((string) $confirmResponse->json('data.checkout.summary.payment_status_description'))
        ->toContain('pacote')
        ->not->toContain('gateway');
});

it('returns a semantic failed summary when the credit card checkout is rejected', function () {
    configurePayloadContractPagarme();

    Http::preventStrayRequests();
    Http::fake([
        'https://api.pagar.me/core/v5/customers' => Http::response([
            'id' => 'cus_contract_failed_123',
            'email' => 'camila@example.com',
        ], 200),
        'https://api.pagar.me/core/v5/customers/cus_contract_failed_123/cards' => Http::response([
            'id' => 'card_contract_failed_123',
        ], 200),
        'https://api.pagar.me/core/v5/orders' => Http::response([
            'id' => 'or_contract_failed_123',
            'code' => 'gateway-order-contract-failed-123',
            'status' => 'failed',
            'charges' => [
                [
                    'id' => 'ch_contract_failed_123',
                    'status' => 'failed',
                    'payment_method' => 'credit_card',
                    'last_transaction' => [
                        'id' => 'tx_contract_failed_123',
                        'status' => 'failed',
                        'acquirer_message' => 'Nao autorizado',
                        'acquirer_return_code' => '51',
                    ],
                ],
            ],
        ], 200),
    ]);

    $package = createPayloadContractPublicEventPackage([
        'target_audience' => EventPackageAudience::DirectCustomer->value,
        'amount_cents' => 24900,
    ]);

    $response = $this->apiPost('/public/event-checkouts', [
        'responsible_name' => 'Camila Rocha',
        'whatsapp' => '(48) 99977-1111',
        'email' => 'camila@example.com',
        'package_id' => $package->id,
        'event' => [
            'title' => 'Casamento Camila & Bruno',
            'event_type' => 'wedding',
        ],
        'payer' => [
            'name' => 'Camila Rocha',
            'email' => 'camila@example.com',
            'document' => '12345678909',
            'document_type' => 'CPF',
            'phone' => '(48) 99977-1111',
            'address' => [
                'street' => 'Rua Exemplo',
                'number' => '123',
                'district' => 'Centro',
                'zip_code' => '88000000',
                'city' => 'Florianopolis',
                'state' => 'SC',
                'country' => 'BR',
            ],
        ],
        'payment' => [
            'method' => 'credit_card',
            'credit_card' => [
                'installments' => 1,
                'statement_descriptor' => 'EVENTOVIVO',
                'card_token' => 'tok_test_contract_failed_123',
                'billing_address' => [
                    'street' => 'Rua Exemplo',
                    'number' => '123',
                    'district' => 'Centro',
                    'zip_code' => '88000000',
                    'city' => 'Florianopolis',
                    'state' => 'SC',
                    'country' => 'BR',
                ],
            ],
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    $response->assertJsonPath('data.checkout.summary.state', 'failed');
    $response->assertJsonPath('data.checkout.summary.tone', 'error');
    $response->assertJsonPath('data.checkout.summary.payment_status_title', 'Pagamento nao confirmado');
    $response->assertJsonPath('data.checkout.summary.order_status_label', 'Pedido nao confirmado');
    $response->assertJsonPath('data.checkout.summary.payment_status_label', 'Nao confirmado');
    $response->assertJsonPath('data.checkout.summary.next_action', 'retry_payment');
    $response->assertJsonPath('data.checkout.summary.is_waiting_payment', false);
    $response->assertJsonPath('data.checkout.summary.can_retry', true);

    expect($response->json('data.checkout.summary.payment_status_description'))->toBe('Nao autorizado');
});

function configurePayloadContractPagarme(): void
{
    config()->set('billing.gateways.event_package', 'pagarme');
    config()->set('services.pagarme', [
        'base_url' => 'https://api.pagar.me/core/v5/',
        'secret_key' => 'sk_test_7611662845434f72bdb0986b69d54ce1',
        'public_key' => 'pk_test_jGWvy7PhpBukl396',
        'statement_descriptor' => 'EVENTOVIVO',
        'pix_expires_in' => 1800,
        'timeout' => 15,
        'connect_timeout' => 5,
        'retry_times' => 1,
        'retry_sleep_ms' => 0,
    ]);
}

function createPayloadContractPublicEventPackage(array $overrides = []): EventPackage
{
    $package = EventPackage::factory()->create([
        'target_audience' => $overrides['target_audience'] ?? EventPackageAudience::Both->value,
        'is_active' => $overrides['is_active'] ?? true,
    ]);

    $package->prices()->create([
        'billing_mode' => EventPackageBillingMode::OneTime->value,
        'currency' => 'BRL',
        'amount_cents' => $overrides['amount_cents'] ?? 19900,
        'is_active' => true,
        'is_default' => true,
    ]);

    foreach (($overrides['features'] ?? [
        'hub.enabled' => 'true',
        'wall.enabled' => 'true',
        'play.enabled' => 'false',
        'media.retention_days' => '60',
        'media.max_photos' => '300',
    ]) as $featureKey => $featureValue) {
        $package->features()->create([
            'feature_key' => $featureKey,
            'feature_value' => $featureValue,
        ]);
    }

    return $package->fresh(['prices', 'features']);
}
