<?php

use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Services\Pagarme\PagarmeOrderPayloadFactory;

beforeEach(function () {
    config()->set('services.pagarme', [
        'statement_descriptor' => 'EVENTOVIVO',
        'pix_expires_in' => 1800,
    ]);
});

it('builds the pagarme order payload for pix checkouts', function () {
    $order = BillingOrder::factory()->create([
        'uuid' => '0d8a2e1d-1b6d-4c77-b3a0-4d457c2d1111',
        'payment_method' => 'pix',
        'total_cents' => 19900,
        'metadata_json' => [
            'journey' => 'public_event_checkout',
            'package_id' => 12,
            'package_code' => 'pkg-12',
            'payment' => [
                'method' => 'pix',
                'pix' => [
                    'expires_in' => 1800,
                ],
            ],
        ],
        'customer_snapshot_json' => [
            'name' => 'Mariana Alves',
            'email' => 'mariana@example.com',
            'document' => '12345678909',
            'document_type' => 'CPF',
            'phone' => '5548999881111',
            'address' => [
                'street' => 'Rua Exemplo',
                'number' => '123',
                'district' => 'Centro',
                'complement' => 'Sala 2',
                'zip_code' => '88000000',
                'city' => 'Florianopolis',
                'state' => 'SC',
                'country' => 'BR',
            ],
        ],
    ]);

    $order->items()->create([
        'item_type' => 'event_package',
        'reference_id' => 12,
        'description' => 'Pacote Evento Premium',
        'quantity' => 1,
        'unit_amount_cents' => 19900,
        'total_amount_cents' => 19900,
        'snapshot_json' => [],
    ]);

    $payload = app(PagarmeOrderPayloadFactory::class)->build($order, [
        'payment' => $order->metadata_json['payment'],
        'payer' => $order->customer_snapshot_json,
    ]);

    expect($payload['code'])->toBe('0d8a2e1d-1b6d-4c77-b3a0-4d457c2d1111');
    expect($payload['closed'])->toBeTrue();
    expect(data_get($payload, 'customer.name'))->toBe('Mariana Alves');
    expect(data_get($payload, 'payments.0.payment_method'))->toBe('pix');
    expect(data_get($payload, 'payments.0.pix.expires_in'))->toBe(1800);
    expect($payload['metadata'])->toMatchArray([
        'billing_order_uuid' => '0d8a2e1d-1b6d-4c77-b3a0-4d457c2d1111',
        'billing_order_id' => $order->id,
        'event_id' => $order->event_id,
        'organization_id' => $order->organization_id,
        'package_id' => 12,
        'journey' => 'public_event_checkout',
    ]);

    expect($payload['items'][0])->toMatchArray([
        'code' => 'pkg-12',
        'amount' => 19900,
        'description' => 'Pacote Evento Premium',
        'quantity' => 1,
    ]);
});

it('builds the pagarme order payload for credit card checkouts', function () {
    $order = BillingOrder::factory()->create([
        'uuid' => '0d8a2e1d-1b6d-4c77-b3a0-4d457c2d1112',
        'payment_method' => 'credit_card',
        'total_cents' => 19900,
        'metadata_json' => [
            'journey' => 'public_event_checkout',
            'package_id' => 12,
            'package_code' => 'pkg-12',
            'payment' => [
                'method' => 'credit_card',
                'credit_card' => [
                    'installments' => 1,
                    'statement_descriptor' => 'EVENTOVIVO',
                    'card_token' => 'tok_test_123',
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
        ],
        'customer_snapshot_json' => [
            'name' => 'Camila Rocha',
            'email' => 'camila@example.com',
            'document' => '12345678909',
            'document_type' => 'CPF',
            'phone' => '5548999771111',
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
    ]);

    $order->items()->create([
        'item_type' => 'event_package',
        'reference_id' => 12,
        'description' => 'Pacote Evento Premium',
        'quantity' => 1,
        'unit_amount_cents' => 19900,
        'total_amount_cents' => 19900,
        'snapshot_json' => [],
    ]);

    $payload = app(PagarmeOrderPayloadFactory::class)->build($order, [
        'payment' => [
            'method' => 'credit_card',
            'credit_card' => [
                'installments' => 1,
                'statement_descriptor' => 'EVENTOVIVO',
                'card_token' => 'tok_test_123',
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
        'payer' => $order->customer_snapshot_json,
    ]);

    expect(data_get($payload, 'payments.0.payment_method'))->toBe('credit_card');
    expect(data_get($payload, 'payments.0.credit_card.installments'))->toBe(1);
    expect(data_get($payload, 'payments.0.credit_card.statement_descriptor'))->toBe('EVENTOVIVO');
    expect(data_get($payload, 'payments.0.credit_card.operation_type'))->toBe('auth_and_capture');
    expect(data_get($payload, 'payments.0.credit_card.card_token'))->toBe('tok_test_123');

    expect(data_get($payload, 'payments.0.credit_card.billing_address'))->toMatchArray([
        'line_1' => 'Rua Exemplo, 123, Centro',
        'zip_code' => '88000000',
        'city' => 'Florianopolis',
        'state' => 'SC',
        'country' => 'BR',
    ]);
});

it('builds the pagarme order payload for psp credit card checkouts using customer_id and card_id', function () {
    $order = BillingOrder::factory()->create([
        'uuid' => '0d8a2e1d-1b6d-4c77-b3a0-4d457c2d1113',
        'payment_method' => 'credit_card',
        'total_cents' => 19900,
        'metadata_json' => [
            'journey' => 'public_event_checkout',
            'package_id' => 12,
            'package_code' => 'pkg-12',
        ],
        'customer_snapshot_json' => [
            'name' => 'Camila Rocha',
            'email' => 'camila@example.com',
            'document' => '12345678909',
            'document_type' => 'CPF',
            'phone' => '5548999771111',
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
    ]);

    $order->items()->create([
        'item_type' => 'event_package',
        'reference_id' => 12,
        'description' => 'Pacote Evento Premium',
        'quantity' => 1,
        'unit_amount_cents' => 19900,
        'total_amount_cents' => 19900,
        'snapshot_json' => [],
    ]);

    $payload = app(PagarmeOrderPayloadFactory::class)->build($order, [
        'payment' => [
            'method' => 'credit_card',
            'credit_card' => [
                'installments' => 1,
                'statement_descriptor' => 'EVENTOVIVO',
                'card_token' => 'tok_test_123',
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
        'payer' => $order->customer_snapshot_json,
        'gateway_customer_id' => 'cus_test_123',
        'gateway_card_id' => 'card_test_123',
    ]);

    expect($payload['customer_id'])->toBe('cus_test_123');
    expect($payload)->not()->toHaveKey('customer');
    expect(data_get($payload, 'payments.0.credit_card.card_id'))->toBe('card_test_123');
    expect(data_get($payload, 'payments.0.credit_card.card_token'))->toBeNull();
});
