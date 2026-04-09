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

it('adds recurring billing endpoints for plans subscriptions cycles invoices charges hooks and wallet cards', function () {
    Http::preventStrayRequests();

    Http::fake(function (Request $request) {
        $url = $request->url();
        $method = $request->method();

        return match (true) {
            $method === 'POST' && $url === 'https://api.pagar.me/core/v5/plans' => Http::response([
                'id' => 'plan_test_123',
                'name' => 'Eventovivo Pro Mensal',
            ], 200),
            $method === 'GET' && str_starts_with($url, 'https://api.pagar.me/core/v5/plans?') => Http::response([
                'data' => [['id' => 'plan_test_123']],
            ], 200),
            $method === 'GET' && $url === 'https://api.pagar.me/core/v5/plans/plan_test_123' => Http::response([
                'id' => 'plan_test_123',
                'interval' => 'month',
            ], 200),
            $method === 'POST' && $url === 'https://api.pagar.me/core/v5/subscriptions' => Http::response([
                'id' => 'sub_test_123',
                'status' => 'active',
            ], 200),
            $method === 'GET' && $url === 'https://api.pagar.me/core/v5/subscriptions/sub_test_123' => Http::response([
                'id' => 'sub_test_123',
                'status' => 'active',
            ], 200),
            $method === 'GET' && str_starts_with($url, 'https://api.pagar.me/core/v5/subscriptions?') => Http::response([
                'data' => [['id' => 'sub_test_123']],
            ], 200),
            $method === 'GET' && str_starts_with($url, 'https://api.pagar.me/core/v5/subscriptions/sub_test_123/cycles?') => Http::response([
                'data' => [['id' => 'cycle_test_123']],
            ], 200),
            $method === 'GET' && str_starts_with($url, 'https://api.pagar.me/core/v5/invoices?') => Http::response([
                'data' => [['id' => 'inv_test_123']],
            ], 200),
            $method === 'GET' && str_starts_with($url, 'https://api.pagar.me/core/v5/charges?') => Http::response([
                'data' => [['id' => 'ch_test_123']],
            ], 200),
            $method === 'GET' && $url === 'https://api.pagar.me/core/v5/hooks/hook_test_123' => Http::response([
                'id' => 'hook_test_123',
                'status' => 'failed',
            ], 200),
            $method === 'GET' && $url === 'https://api.pagar.me/core/v5/customers/cus_test_123/cards' => Http::response([
                'data' => [['id' => 'card_test_123']],
            ], 200),
            $method === 'DELETE' && $url === 'https://api.pagar.me/core/v5/subscriptions/sub_test_123' => Http::response([
                'id' => 'sub_test_123',
                'status' => 'canceled',
            ], 200),
            default => Http::response([
                'message' => "Unexpected request: {$method} {$url}",
            ], 500),
        };
    });

    $client = app(PagarmeClient::class);

    $plan = $client->createPlan([
        'name' => 'Eventovivo Pro Mensal',
    ], 'idem-plan-123');
    $plans = $client->listPlans([
        'page' => 1,
        'size' => 20,
    ]);
    $fetchedPlan = $client->getPlan('plan_test_123');
    $subscription = $client->createSubscription([
        'plan_id' => 'plan_test_123',
        'payment_method' => 'credit_card',
    ], 'idem-sub-123');
    $fetchedSubscription = $client->getSubscription('sub_test_123');
    $subscriptions = $client->listSubscriptions([
        'status' => 'active',
        'page' => 1,
        'size' => 20,
    ]);
    $cycles = $client->listSubscriptionCycles('sub_test_123', [
        'page' => 1,
        'size' => 20,
    ]);
    $invoices = $client->listInvoices([
        'subscription_id' => 'sub_test_123',
        'page' => 1,
        'size' => 20,
    ]);
    $charges = $client->listCharges([
        'customer_id' => 'cus_test_123',
        'page' => 1,
        'size' => 20,
    ]);
    $hook = $client->getHook('hook_test_123');
    $cards = $client->listCustomerCards('cus_test_123');
    $canceledSubscription = $client->cancelSubscription('sub_test_123', [
        'cancel_pending_invoices' => true,
    ]);

    expect($plan['id'])->toBe('plan_test_123')
        ->and(data_get($plans, 'data.0.id'))->toBe('plan_test_123')
        ->and($fetchedPlan['interval'])->toBe('month')
        ->and($subscription['id'])->toBe('sub_test_123')
        ->and($fetchedSubscription['status'])->toBe('active')
        ->and(data_get($subscriptions, 'data.0.id'))->toBe('sub_test_123')
        ->and(data_get($cycles, 'data.0.id'))->toBe('cycle_test_123')
        ->and(data_get($invoices, 'data.0.id'))->toBe('inv_test_123')
        ->and(data_get($charges, 'data.0.id'))->toBe('ch_test_123')
        ->and($hook['status'])->toBe('failed')
        ->and(data_get($cards, 'data.0.id'))->toBe('card_test_123')
        ->and($canceledSubscription['status'])->toBe('canceled');

    Http::assertSent(function (Request $request) {
        return $request->method() === 'GET'
            && str_contains($request->url(), '/subscriptions/sub_test_123/cycles?')
            && str_contains($request->url(), 'page=1')
            && str_contains($request->url(), 'size=20');
    });

    Http::assertSent(function (Request $request) {
        return $request->method() === 'GET'
            && str_contains($request->url(), '/invoices?')
            && str_contains($request->url(), 'subscription_id=sub_test_123');
    });

    Http::assertSent(function (Request $request) {
        return $request->method() === 'GET'
            && $request->url() === 'https://api.pagar.me/core/v5/customers/cus_test_123/cards';
    });
});

it('applies idempotency keys across recurring write operations, not only orders', function () {
    Http::preventStrayRequests();

    Http::fake(function (Request $request) {
        $url = $request->url();
        $method = $request->method();

        return match (true) {
            $method === 'POST' && $url === 'https://api.pagar.me/core/v5/plans' => Http::response(['id' => 'plan_test_123'], 200),
            $method === 'POST' && $url === 'https://api.pagar.me/core/v5/subscriptions' => Http::response(['id' => 'sub_test_123'], 200),
            $method === 'PATCH' && $url === 'https://api.pagar.me/core/v5/subscriptions/sub_test_123/card' => Http::response(['id' => 'sub_test_123'], 200),
            $method === 'PATCH' && $url === 'https://api.pagar.me/core/v5/subscriptions/sub_test_123/payment-method' => Http::response(['id' => 'sub_test_123'], 200),
            $method === 'PATCH' && $url === 'https://api.pagar.me/core/v5/subscriptions/sub_test_123/start-at' => Http::response(['id' => 'sub_test_123'], 200),
            $method === 'PATCH' && $url === 'https://api.pagar.me/core/v5/subscriptions/sub_test_123/metadata' => Http::response(['id' => 'sub_test_123'], 200),
            default => Http::response([
                'message' => "Unexpected request: {$method} {$url}",
            ], 500),
        };
    });

    $client = app(PagarmeClient::class);

    $client->createPlan(['name' => 'Plano recorrente'], 'idem-plan-write');
    $client->createSubscription(['plan_id' => 'plan_test_123'], 'idem-sub-write');
    $client->updateSubscriptionCard('sub_test_123', ['card_token' => 'tok_123'], 'idem-card-write');
    $client->updateSubscriptionPaymentMethod('sub_test_123', ['payment_method' => 'credit_card'], 'idem-payment-method-write');
    $client->updateSubscriptionStartAt('sub_test_123', ['start_at' => '2026-04-10T12:00:00Z'], 'idem-start-write');
    $client->updateSubscriptionMetadata('sub_test_123', ['metadata' => ['tenant_id' => '123']], 'idem-metadata-write');

    $expectedHeaders = [
        'https://api.pagar.me/core/v5/plans' => 'idem-plan-write',
        'https://api.pagar.me/core/v5/subscriptions' => 'idem-sub-write',
        'https://api.pagar.me/core/v5/subscriptions/sub_test_123/card' => 'idem-card-write',
        'https://api.pagar.me/core/v5/subscriptions/sub_test_123/payment-method' => 'idem-payment-method-write',
        'https://api.pagar.me/core/v5/subscriptions/sub_test_123/start-at' => 'idem-start-write',
        'https://api.pagar.me/core/v5/subscriptions/sub_test_123/metadata' => 'idem-metadata-write',
    ];

    foreach ($expectedHeaders as $url => $idempotencyKey) {
        Http::assertSent(function (Request $request) use ($url, $idempotencyKey) {
            return $request->url() === $url
                && ($request->header('Idempotency-Key')[0] ?? null) === $idempotencyKey;
        });
    }
});
