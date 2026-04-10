<?php

use App\Modules\Billing\Services\Pagarme\PagarmeClient;
use App\Modules\Billing\Services\Pagarme\PagarmeHomologationService;
use Illuminate\Support\Facades\Http;

it('builds a pix cancel probe by creating the order, canceling the charge and collecting snapshots', function () {
    $client = Mockery::mock(PagarmeClient::class);
    $service = new PagarmeHomologationService($client);

    $createdOrder = [
        'id' => 'or_probe_pix_123',
        'status' => 'pending',
        'charges' => [
            [
                'id' => 'ch_probe_pix_123',
            ],
        ],
    ];
    $cancelResponse = [
        'id' => 'ch_probe_pix_123',
        'status' => 'canceled',
        'last_transaction' => [
            'id' => 'tx_probe_pix_cancel_123',
        ],
    ];
    $orderSnapshot = [
        'id' => 'or_probe_pix_123',
        'status' => 'canceled',
    ];
    $chargeSnapshot = [
        'id' => 'ch_probe_pix_123',
        'status' => 'canceled',
    ];

    $client->shouldReceive('createOrder')
        ->once()
        ->withArgs(function (array $payload, string $idempotencyKey) {
            expect($payload['payments'][0]['payment_method'])->toBe('pix')
                ->and($payload['metadata']['probe'])->toBe('pix_cancel')
                ->and($idempotencyKey)->toStartWith('pagarme-homologation:probe-pix-cancel-');

            return true;
        })
        ->andReturn($createdOrder);
    $client->shouldReceive('cancelCharge')
        ->once()
        ->with('ch_probe_pix_123')
        ->andReturn($cancelResponse);
    $client->shouldReceive('getOrder')
        ->twice()
        ->with('or_probe_pix_123')
        ->andReturn($orderSnapshot);
    $client->shouldReceive('getCharge')
        ->twice()
        ->with('ch_probe_pix_123')
        ->andReturn($chargeSnapshot);

    $result = $service->runPixCancelProbe(
        amountCents: 19900,
        code: 'probe-pix-cancel-fixed',
        pollAttempts: 2,
        pollSleepMs: 0,
    );

    expect($result['scenario'])->toBe('pix_cancel')
        ->and($result['probe_code'])->toBe('probe-pix-cancel-fixed')
        ->and($result['created_order']['id'])->toBe('or_probe_pix_123')
        ->and($result['cancel_response']['status'])->toBe('canceled')
        ->and($result['snapshots'])->toHaveCount(2)
        ->and($result['snapshots'][0]['order']['status'])->toBe('canceled')
        ->and($result['snapshots'][0]['charge']['status'])->toBe('canceled');
});

it('builds the gateway simulator dossier with the documented card matrix', function () {
    $client = Mockery::mock(PagarmeClient::class);
    $service = new PagarmeHomologationService($client);

    $cards = [
        '4000000000000036' => 'processing_to_paid',
        '4000000000000044' => 'processing_to_failed',
        '4000000000000069' => 'paid_to_chargedback',
    ];

    foreach ($cards as $cardNumber => $expectedScenario) {
        $orderId = 'or_' . substr($cardNumber, -4);
        $chargeId = 'ch_' . substr($cardNumber, -4);

        $client->shouldReceive('createOrder')
            ->once()
            ->withArgs(function (array $payload, string $idempotencyKey) use ($cardNumber, $expectedScenario) {
                expect($payload['payments'][0]['payment_method'])->toBe('credit_card')
                    ->and((string) $payload['payments'][0]['credit_card']['card']['number'])->toBe((string) $cardNumber)
                    ->and($payload['metadata']['probe'])->toBe('card_homologation')
                    ->and($idempotencyKey)->toStartWith("pagarme-homologation:probe-{$expectedScenario}-");

                return true;
            })
            ->andReturn([
                'id' => $orderId,
                'status' => 'paid',
                'charges' => [
                    ['id' => $chargeId],
                ],
            ]);
        $client->shouldReceive('getOrder')
            ->once()
            ->with($orderId)
            ->andReturn(['id' => $orderId, 'status' => 'paid']);
        $client->shouldReceive('getCharge')
            ->once()
            ->with($chargeId)
            ->andReturn(['id' => $chargeId, 'status' => 'paid']);
    }

    $result = $service->runGatewaySimulatorDossier(
        amountCents: 19900,
        cvv: '123',
        pollAttempts: 1,
        pollSleepMs: 0,
    );

    expect($result['scenario'])->toBe('gateway_credit_card_simulator_dossier')
        ->and($result['results'])->toHaveCount(3)
        ->and($result['results'][0]['documented_scenario'])->toBe('processing_to_paid')
        ->and($result['results'][1]['documented_scenario'])->toBe('processing_to_failed')
        ->and($result['results'][2]['documented_scenario'])->toBe('paid_to_chargedback');
});

it('builds the recurring lifecycle homologation probe end to end', function () {
    config()->set('services.pagarme', [
        'base_url' => 'https://api.pagar.me/core/v5/',
        'secret_key' => 'sk_test_123',
        'public_key' => 'pk_test_123',
        'statement_descriptor' => 'EVENTOVIVO',
        'timeout' => 15,
        'connect_timeout' => 5,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://api.pagar.me/core/v5/tokens?appId=pk_test_123' => Http::sequence()
            ->push(['id' => 'tok_first_123', 'type' => 'card'], 200)
            ->push(['id' => 'tok_second_123', 'type' => 'card'], 200),
    ]);

    $client = Mockery::mock(PagarmeClient::class);
    $service = new PagarmeHomologationService($client);

    $client->shouldReceive('createCustomer')
        ->once()
        ->withArgs(fn (array $payload) => data_get($payload, 'email') !== 'camila.rocha@example.com')
        ->andReturn(['id' => 'cus_lifecycle_123']);
    $client->shouldReceive('createCustomerCard')
        ->once()
        ->with('cus_lifecycle_123', Mockery::on(fn (array $payload) => $payload['token'] === 'tok_first_123'))
        ->andReturn(['id' => 'card_first_123']);
    $client->shouldReceive('createCustomerCard')
        ->once()
        ->with('cus_lifecycle_123', Mockery::on(fn (array $payload) => $payload['token'] === 'tok_second_123'))
        ->andReturn(['id' => 'card_second_123']);
    $client->shouldReceive('createPlan')
        ->once()
        ->withArgs(function (array $payload, string $idempotencyKey) {
            expect($payload['payment_methods'])->toBe(['credit_card'])
                ->and($payload['installments'])->toBe([1])
                ->and($payload['billing_type'])->toBe('prepaid')
                ->and($payload['items'][0]['pricing_scheme']['price'])->toBe(19900)
                ->and($idempotencyKey)->toStartWith('pagarme-homologation:probe-recurring-lifecycle-');

            return true;
        })
        ->andReturn(['id' => 'plan_lifecycle_123']);
    $client->shouldReceive('createSubscription')
        ->once()
        ->withArgs(function (array $payload, string $idempotencyKey) {
            expect($payload['plan_id'])->toBe('plan_lifecycle_123')
                ->and($payload['payment_method'])->toBe('credit_card')
                ->and($payload['customer_id'])->toBe('cus_lifecycle_123')
                ->and($payload['card_id'])->toBe('card_first_123')
                ->and($payload['installments'])->toBe(1)
                ->and($idempotencyKey)->toStartWith('pagarme-homologation:probe-recurring-lifecycle-');

            return true;
        })
        ->andReturn(['id' => 'sub_lifecycle_123', 'status' => 'active']);
    $client->shouldReceive('getSubscription')
        ->times(3)
        ->with('sub_lifecycle_123')
        ->andReturn(['id' => 'sub_lifecycle_123', 'status' => 'active']);
    $client->shouldReceive('listSubscriptionCycles')
        ->twice()
        ->with('sub_lifecycle_123', ['page' => 1, 'size' => 20])
        ->andReturn(['data' => [['id' => 'cy_lifecycle_123']]]);
    $client->shouldReceive('listInvoices')
        ->twice()
        ->with(['subscription_id' => 'sub_lifecycle_123', 'page' => 1, 'size' => 20])
        ->andReturn(['data' => [['id' => 'inv_lifecycle_123', 'charge' => ['id' => 'ch_lifecycle_123']]]]);
    $client->shouldReceive('listCharges')
        ->twice()
        ->with(['customer_id' => 'cus_lifecycle_123', 'page' => 1, 'size' => 20])
        ->andReturn(['data' => [['id' => 'ch_lifecycle_123']]]);
    $client->shouldReceive('getCharge')
        ->twice()
        ->with('ch_lifecycle_123')
        ->andReturn(['id' => 'ch_lifecycle_123', 'status' => 'paid']);
    $client->shouldReceive('listCustomerCards')
        ->twice()
        ->with('cus_lifecycle_123')
        ->andReturn(['data' => [['id' => 'card_first_123'], ['id' => 'card_second_123']]]);
    $client->shouldReceive('updateSubscriptionCard')
        ->once()
        ->withArgs(fn (string $subscriptionId, array $payload, string $idempotencyKey) => $subscriptionId === 'sub_lifecycle_123'
            && $payload['card_id'] === 'card_second_123'
            && str_starts_with($idempotencyKey, 'pagarme-homologation:probe-recurring-lifecycle-'))
        ->andReturn(['id' => 'sub_lifecycle_123', 'status' => 'active', 'card' => ['id' => 'card_second_123']]);
    $client->shouldReceive('cancelSubscription')
        ->once()
        ->with('sub_lifecycle_123', ['cancel_pending_invoices' => true])
        ->andReturn(['id' => 'sub_lifecycle_123', 'status' => 'canceled']);
    $client->shouldReceive('getHook')
        ->once()
        ->with('hook_lifecycle_123')
        ->andReturn(['id' => 'hook_lifecycle_123']);

    $result = $service->runRecurringLifecycleProbe(
        amountCents: 19900,
        hookId: 'hook_lifecycle_123',
        pollAttempts: 1,
        pollSleepMs: 0,
    );

    expect($result['scenario'])->toBe('recurring_lifecycle')
        ->and($result['customer']['id'])->toBe('cus_lifecycle_123')
        ->and($result['cards']['first']['id'])->toBe('card_first_123')
        ->and($result['subscription']['id'])->toBe('sub_lifecycle_123')
        ->and($result['card_update']['card']['id'])->toBe('card_second_123')
        ->and($result['cancellation']['status'])->toBe('canceled')
        ->and($result['hook_snapshot']['hook']['id'])->toBe('hook_lifecycle_123');
});
