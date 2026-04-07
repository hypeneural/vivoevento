<?php

namespace App\Modules\Billing\Services\Pagarme;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PagarmeHomologationService
{
    private const DEFAULT_POLL_ATTEMPTS = 4;
    private const DEFAULT_POLL_SLEEP_MS = 1500;

    /**
     * These card scenarios come from the official Gateway credit-card simulator docs.
     * They are intentionally isolated from the production checkout flow.
     */
    private const CREDIT_CARD_SIMULATOR_SCENARIOS = [
        '4000000000000036' => 'processing_to_paid',
        '4000000000000044' => 'processing_to_failed',
        '4000000000000069' => 'paid_to_chargedback',
    ];

    public function __construct(
        private readonly PagarmeClient $client,
    ) {}

    public function runPixCancelProbe(
        int $amountCents = 19900,
        ?string $code = null,
        int $pollAttempts = self::DEFAULT_POLL_ATTEMPTS,
        int $pollSleepMs = self::DEFAULT_POLL_SLEEP_MS,
    ): array {
        $probeCode = $code ?: $this->buildProbeCode('pix-cancel');
        $idempotencyKey = $this->buildIdempotencyKey($probeCode);
        $createdOrder = $this->client->createOrder(
            $this->buildPixOrderPayload($probeCode, $amountCents),
            $idempotencyKey,
        );

        $chargeId = (string) ($createdOrder['charges'][0]['id'] ?? '');
        $orderId = (string) ($createdOrder['id'] ?? '');
        $canceledCharge = $this->client->cancelCharge($chargeId);

        return [
            'scenario' => 'pix_cancel',
            'probe_code' => $probeCode,
            'idempotency_key' => $idempotencyKey,
            'created_order' => $createdOrder,
            'cancel_response' => $canceledCharge,
            'snapshots' => $this->pollSnapshots($orderId, $chargeId, $pollAttempts, $pollSleepMs),
            'observations' => [
                'Direct gateway probe using POST /orders + DELETE /charges/{charge_id}.',
                'Expected by docs: Pix charge cancellation/refund is performed through DELETE /charges/{charge_id}.',
            ],
        ];
    }

    public function runCreditCardRefundProbe(
        string $cardNumber = '4000000000000010',
        string $cvv = '123',
        int $amountCents = 19900,
        ?string $code = null,
        int $pollAttempts = self::DEFAULT_POLL_ATTEMPTS,
        int $pollSleepMs = self::DEFAULT_POLL_SLEEP_MS,
    ): array {
        $probeCode = $code ?: $this->buildProbeCode('card-refund');
        $idempotencyKey = $this->buildIdempotencyKey($probeCode);
        $createdOrder = $this->client->createOrder(
            $this->buildCreditCardOrderPayload($probeCode, $amountCents, $cardNumber, $cvv),
            $idempotencyKey,
        );

        $chargeId = (string) ($createdOrder['charges'][0]['id'] ?? '');
        $orderId = (string) ($createdOrder['id'] ?? '');
        $canceledCharge = $this->client->cancelCharge($chargeId);

        return [
            'scenario' => 'card_refund',
            'probe_code' => $probeCode,
            'idempotency_key' => $idempotencyKey,
            'card_number' => $cardNumber,
            'created_order' => $createdOrder,
            'cancel_response' => $canceledCharge,
            'snapshots' => $this->pollSnapshots($orderId, $chargeId, $pollAttempts, $pollSleepMs),
            'observations' => [
                'Direct gateway probe using POST /orders with a test card + DELETE /charges/{charge_id}.',
                'Expected by docs: cancel/refund uses DELETE /charges/{charge_id}.',
            ],
        ];
    }

    public function runGatewaySimulatorDossier(
        int $amountCents = 19900,
        string $cvv = '123',
        int $pollAttempts = self::DEFAULT_POLL_ATTEMPTS,
        int $pollSleepMs = self::DEFAULT_POLL_SLEEP_MS,
    ): array {
        $results = [];

        foreach (self::CREDIT_CARD_SIMULATOR_SCENARIOS as $cardNumber => $expectedScenario) {
            $probeCode = $this->buildProbeCode($expectedScenario);
            $idempotencyKey = $this->buildIdempotencyKey($probeCode);
            $createdOrder = $this->client->createOrder(
                $this->buildCreditCardOrderPayload($probeCode, $amountCents, $cardNumber, $cvv),
                $idempotencyKey,
            );

            $orderId = (string) ($createdOrder['id'] ?? '');
            $chargeId = (string) ($createdOrder['charges'][0]['id'] ?? '');

            $results[] = [
                'card_number' => $cardNumber,
                'documented_scenario' => $expectedScenario,
                'probe_code' => $probeCode,
                'idempotency_key' => $idempotencyKey,
                'created_order' => $createdOrder,
                'snapshots' => $this->pollSnapshots($orderId, $chargeId, $pollAttempts, $pollSleepMs),
            ];
        }

        return [
            'scenario' => 'gateway_credit_card_simulator_dossier',
            'docs_expectation' => [
                '4000000000000036' => 'processing -> paid',
                '4000000000000044' => 'processing -> failed',
                '4000000000000069' => 'paid -> chargedback',
            ],
            'results' => $results,
            'observations' => [
                'The official docs label these three numbers under the Gateway credit-card simulator.',
                'If the current account/flow is PSP, divergence is expected and must be documented as a simulator-context mismatch.',
            ],
        ];
    }

    private function pollSnapshots(
        string $orderId,
        string $chargeId,
        int $attempts,
        int $sleepMs,
    ): array {
        $snapshots = [];
        $totalAttempts = max(1, $attempts);

        for ($attempt = 1; $attempt <= $totalAttempts; $attempt++) {
            $order = $this->client->getOrder($orderId);
            $charge = $this->client->getCharge($chargeId);

            $snapshots[] = [
                'attempt' => $attempt,
                'captured_at' => now()->toISOString(),
                'order' => $order,
                'charge' => $charge,
            ];

            if ($attempt < $totalAttempts && $sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        return $snapshots;
    }

    private function buildPixOrderPayload(string $code, int $amountCents): array
    {
        return [
            'code' => $code,
            'items' => [
                [
                    'amount' => $amountCents,
                    'description' => 'Probe de homologacao Pix cancel',
                    'quantity' => 1,
                    'code' => 'probe-pix-cancel',
                ],
            ],
            'customer' => $this->defaultCustomer(),
            'payments' => [
                [
                    'payment_method' => 'pix',
                    'pix' => [
                        'expires_in' => (int) config('services.pagarme.pix_expires_in', 1800),
                        'additional_information' => [
                            ['name' => 'Probe', 'value' => $code],
                        ],
                    ],
                ],
            ],
            'metadata' => [
                'probe' => 'pix_cancel',
                'probe_code' => $code,
            ],
        ];
    }

    private function buildCreditCardOrderPayload(
        string $code,
        int $amountCents,
        string $cardNumber,
        string $cvv,
    ): array {
        return [
            'code' => $code,
            'items' => [
                [
                    'amount' => $amountCents,
                    'description' => 'Probe de homologacao cartao',
                    'quantity' => 1,
                    'code' => 'probe-card',
                ],
            ],
            'customer' => $this->defaultCustomer(),
            'payments' => [
                [
                    'payment_method' => 'credit_card',
                    'credit_card' => [
                        'installments' => 1,
                        'statement_descriptor' => (string) config('services.pagarme.statement_descriptor', 'EVENTOVIVO'),
                        'operation_type' => 'auth_and_capture',
                        'card' => [
                            'number' => $cardNumber,
                            'holder_name' => 'CAMILA ROCHA',
                            'exp_month' => 12,
                            'exp_year' => 30,
                            'cvv' => $cvv,
                            'billing_address' => $this->defaultBillingAddress(),
                        ],
                    ],
                ],
            ],
            'metadata' => [
                'probe' => 'card_homologation',
                'probe_code' => $code,
                'card_last4' => substr($cardNumber, -4),
            ],
        ];
    }

    private function defaultCustomer(): array
    {
        return [
            'name' => 'Camila Rocha',
            'email' => 'camila.rocha@example.com',
            'type' => 'individual',
            'document' => '12345678909',
            'phones' => [
                'mobile_phone' => [
                    'country_code' => '55',
                    'area_code' => '48',
                    'number' => '999771111',
                ],
            ],
            'address' => $this->defaultBillingAddress() + [
                'line_2' => 'Sala 2',
            ],
        ];
    }

    private function defaultBillingAddress(): array
    {
        return [
            'line_1' => 'Rua Exemplo, 123, Centro',
            'zip_code' => '88000000',
            'city' => 'Florianopolis',
            'state' => 'SC',
            'country' => 'BR',
        ];
    }

    private function buildProbeCode(string $prefix): string
    {
        return sprintf(
            'probe-%s-%s-%s',
            $prefix,
            Carbon::now()->format('YmdHis'),
            Str::lower(Str::random(6)),
        );
    }

    private function buildIdempotencyKey(string $probeCode): string
    {
        return sprintf('pagarme-homologation:%s', $probeCode);
    }
}
