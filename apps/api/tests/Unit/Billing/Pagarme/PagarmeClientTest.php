<?php

use App\Modules\Billing\Services\Pagarme\PagarmeClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.pagarme', [
        'base_url' => 'https://api.pagar.me/core/v5/',
        'secret_key' => 'sk_test_7611662845434f72bdb0986b69d54ce1',
        'public_key' => 'pk_test_jGWvy7PhpBukl396',
        'statement_descriptor' => 'EVENTOVIVO',
        'pix_expires_in' => 1800,
        'timeout' => 15,
        'connect_timeout' => 5,
        'retry_times' => 2,
        'retry_sleep_ms' => 100,
    ]);
});

it('creates an order using basic auth and idempotency key', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.pagar.me/core/v5/orders' => Http::response([
            'id' => 'or_test_123',
            'status' => 'pending',
        ], 200),
    ]);

    $response = app(PagarmeClient::class)->createOrder([
        'code' => 'order-123',
    ], 'idem-order-123');

    expect($response['id'])->toBe('or_test_123');

    Http::assertSent(function (Request $request) {
        $authHeader = $request->header('Authorization')[0] ?? null;

        return $request->method() === 'POST'
            && $request->url() === 'https://api.pagar.me/core/v5/orders'
            && $authHeader === 'Basic '.base64_encode('sk_test_7611662845434f72bdb0986b69d54ce1:')
            && ($request->header('Idempotency-Key')[0] ?? null) === 'idem-order-123'
            && $request['code'] === 'order-123';
    });
});

it('fetches an order and a charge from the configured base url', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.pagar.me/core/v5/orders/or_test_123' => Http::response([
            'id' => 'or_test_123',
            'status' => 'paid',
        ], 200),
        'https://api.pagar.me/core/v5/charges/ch_test_123' => Http::response([
            'id' => 'ch_test_123',
            'status' => 'paid',
        ], 200),
    ]);

    $client = app(PagarmeClient::class);

    $order = $client->getOrder('or_test_123');
    $charge = $client->getCharge('ch_test_123');

    expect($order['status'])->toBe('paid')
        ->and($charge['status'])->toBe('paid');
});

it('creates a customer and a customer card using basic auth', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.pagar.me/core/v5/customers' => Http::response([
            'id' => 'cus_test_123',
            'email' => 'camila@example.com',
        ], 200),
        'https://api.pagar.me/core/v5/customers/cus_test_123/cards' => Http::response([
            'id' => 'card_test_123',
            'last_four_digits' => '0010',
        ], 200),
    ]);

    $client = app(PagarmeClient::class);

    $customer = $client->createCustomer([
        'name' => 'Camila Rocha',
        'email' => 'camila@example.com',
    ]);

    $card = $client->createCustomerCard('cus_test_123', [
        'token' => 'token_test_123',
    ]);

    expect($customer['id'])->toBe('cus_test_123')
        ->and($card['id'])->toBe('card_test_123');

    Http::assertSent(function (Request $request) {
        $authHeader = $request->header('Authorization')[0] ?? null;

        return $request->method() === 'POST'
            && $request->url() === 'https://api.pagar.me/core/v5/customers'
            && $authHeader === 'Basic '.base64_encode('sk_test_7611662845434f72bdb0986b69d54ce1:')
            && $request['email'] === 'camila@example.com';
    });

    Http::assertSent(function (Request $request) {
        $authHeader = $request->header('Authorization')[0] ?? null;

        return $request->method() === 'POST'
            && $request->url() === 'https://api.pagar.me/core/v5/customers/cus_test_123/cards'
            && $authHeader === 'Basic '.base64_encode('sk_test_7611662845434f72bdb0986b69d54ce1:')
            && $request['token'] === 'token_test_123';
    });
});

it('cancels a charge with delete and captures a charge with post', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.pagar.me/core/v5/charges/ch_test_123' => Http::response([
            'id' => 'ch_test_123',
            'status' => 'canceled',
        ], 200),
        'https://api.pagar.me/core/v5/charges/ch_test_123/capture' => Http::response([
            'id' => 'ch_test_123',
            'status' => 'paid',
        ], 200),
    ]);

    $client = app(PagarmeClient::class);

    $canceled = $client->cancelCharge('ch_test_123');
    $captured = $client->captureCharge('ch_test_123', ['amount' => 19900]);

    expect($canceled['status'])->toBe('canceled')
        ->and($captured['status'])->toBe('paid');

    Http::assertSent(function (Request $request) {
        return $request->method() === 'DELETE'
            && $request->url() === 'https://api.pagar.me/core/v5/charges/ch_test_123';
    });

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.pagar.me/core/v5/charges/ch_test_123/capture'
            && $request['amount'] === 19900;
    });
});

it('lists recent hook deliveries and retries a hook delivery', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.pagar.me/core/v5/hooks?page=1&size=20' => Http::response([
            'data' => [
                [
                    'id' => 'hook_test_123',
                    'event' => 'order.paid',
                    'status' => 'failed',
                ],
            ],
        ], 200),
        'https://api.pagar.me/core/v5/hooks/hook_test_123/retry' => Http::response([
            'id' => 'hook_test_123',
            'status' => 'processing',
        ], 200),
    ]);

    $client = app(PagarmeClient::class);

    $hooks = $client->listHooks([
        'page' => 1,
        'size' => 20,
    ]);

    $retry = $client->retryHook('hook_test_123');

    expect(data_get($hooks, 'data.0.id'))->toBe('hook_test_123')
        ->and($retry['status'])->toBe('processing');

    Http::assertSent(function (Request $request) {
        return $request->method() === 'GET'
            && $request->url() === 'https://api.pagar.me/core/v5/hooks?page=1&size=20';
    });

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.pagar.me/core/v5/hooks/hook_test_123/retry';
    });
});

it('throws the underlying request exception when pagarme returns an error response', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://api.pagar.me/core/v5/orders' => Http::response([
            'message' => 'invalid request',
        ], 422),
    ]);

    expect(fn () => app(PagarmeClient::class)->createOrder([
        'code' => 'order-123',
    ]))->toThrow(RequestException::class);
});
