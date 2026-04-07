<?php

use App\Modules\Billing\Services\Pagarme\PagarmeClient;
use App\Modules\Billing\Services\Pagarme\PagarmeHomologationService;

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
