<?php

namespace App\Modules\Billing\Services\Pagarme;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

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

    public function runRecurringLifecycleProbe(
        int $amountCents = 19900,
        ?string $hookId = null,
        int $pollAttempts = self::DEFAULT_POLL_ATTEMPTS,
        int $pollSleepMs = self::DEFAULT_POLL_SLEEP_MS,
    ): array {
        $probeCode = $this->buildProbeCode('recurring-lifecycle');
        $customer = $this->client->createCustomer(array_merge($this->defaultCustomer(), [
            'email' => sprintf('%s@example.com', str_replace('_', '-', Str::slug($probeCode))),
        ]));
        $customerId = (string) ($customer['id'] ?? '');

        if ($customerId === '') {
            throw new RuntimeException('Pagar.me did not return customer id during recurring lifecycle probe.');
        }

        $firstToken = $this->tokenizeCard('4000000000000010', '123');
        $secondToken = $this->tokenizeCard('4000000000000036', '123');
        $firstCard = $this->client->createCustomerCard($customerId, [
            'token' => (string) ($firstToken['id'] ?? ''),
            'billing_address' => $this->defaultBillingAddress(),
        ]);
        $secondCard = $this->client->createCustomerCard($customerId, [
            'token' => (string) ($secondToken['id'] ?? ''),
            'billing_address' => $this->defaultBillingAddress(),
        ]);
        $firstCardId = (string) ($firstCard['id'] ?? '');
        $secondCardId = (string) ($secondCard['id'] ?? '');

        if ($firstCardId === '' || $secondCardId === '') {
            throw new RuntimeException('Pagar.me did not return card ids during recurring lifecycle probe.');
        }

        $plan = $this->client->createPlan(
            $this->buildRecurringPlanPayload($probeCode, $amountCents),
            $this->buildIdempotencyKey("{$probeCode}:plan"),
        );
        $planId = (string) ($plan['id'] ?? '');

        if ($planId === '') {
            throw new RuntimeException('Pagar.me did not return plan id during recurring lifecycle probe.');
        }

        $subscription = $this->client->createSubscription([
            'plan_id' => $planId,
            'payment_method' => 'credit_card',
            'customer_id' => $customerId,
            'card_id' => $firstCardId,
            'installments' => 1,
            'metadata' => [
                'probe' => 'recurring_lifecycle',
                'probe_code' => $probeCode,
            ],
        ], $this->buildIdempotencyKey("{$probeCode}:subscription"));
        $subscriptionId = (string) ($subscription['id'] ?? '');

        if ($subscriptionId === '') {
            throw new RuntimeException('Pagar.me did not return subscription id during recurring lifecycle probe.');
        }

        $snapshotsBeforeCardUpdate = $this->pollRecurringSnapshots($subscriptionId, $customerId, $pollAttempts, $pollSleepMs);
        $walletBeforeCardUpdate = $this->client->listCustomerCards($customerId);
        $cardUpdate = $this->client->updateSubscriptionCard($subscriptionId, [
            'card_id' => $secondCardId,
        ], $this->buildIdempotencyKey("{$probeCode}:card-update"));
        $subscriptionAfterCardUpdate = $this->client->getSubscription($subscriptionId);
        $walletAfterCardUpdate = $this->client->listCustomerCards($customerId);
        $cancellation = $this->client->cancelSubscription($subscriptionId, [
            'cancel_pending_invoices' => true,
        ]);
        $snapshotsAfterCancellation = $this->pollRecurringSnapshots($subscriptionId, $customerId, max(1, min(2, $pollAttempts)), $pollSleepMs);

        return [
            'scenario' => 'recurring_lifecycle',
            'probe_code' => $probeCode,
            'customer' => $customer,
            'tokens' => [
                'first' => $this->redactToken($firstToken),
                'second' => $this->redactToken($secondToken),
            ],
            'cards' => [
                'first' => $firstCard,
                'second' => $secondCard,
            ],
            'plan' => $plan,
            'subscription' => $subscription,
            'snapshots_before_card_update' => $snapshotsBeforeCardUpdate,
            'wallet_before_card_update' => $walletBeforeCardUpdate,
            'card_update' => $cardUpdate,
            'subscription_after_card_update' => $subscriptionAfterCardUpdate,
            'wallet_after_card_update' => $walletAfterCardUpdate,
            'cancellation' => $cancellation,
            'snapshots_after_cancellation' => $snapshotsAfterCancellation,
            'hook_snapshot' => $this->hookSnapshot($hookId),
            'observations' => [
                'Direct homologation of recurring lifecycle: token -> customer wallet -> plan -> subscription -> cycles/invoices/charges -> card update -> cancel.',
                'Webhook delivery still depends on the configured Cloudflare tunnel URL being reachable from Pagar.me during the probe window.',
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

    private function pollRecurringSnapshots(
        string $subscriptionId,
        string $customerId,
        int $attempts,
        int $sleepMs,
    ): array {
        $snapshots = [];
        $totalAttempts = max(1, $attempts);

        for ($attempt = 1; $attempt <= $totalAttempts; $attempt++) {
            $subscription = $this->client->getSubscription($subscriptionId);
            $cycles = $this->client->listSubscriptionCycles($subscriptionId, [
                'page' => 1,
                'size' => 20,
            ]);
            $invoices = $this->client->listInvoices([
                'subscription_id' => $subscriptionId,
                'page' => 1,
                'size' => 20,
            ]);
            $charges = $this->client->listCharges([
                'customer_id' => $customerId,
                'page' => 1,
                'size' => 20,
            ]);
            $chargeDetails = [];

            foreach ($this->extractRecurringChargeIds($invoices, $charges) as $chargeId) {
                $chargeDetails[$chargeId] = $this->client->getCharge($chargeId);
            }

            $snapshots[] = [
                'attempt' => $attempt,
                'captured_at' => now()->toISOString(),
                'subscription' => $subscription,
                'cycles' => $cycles,
                'invoices' => $invoices,
                'charges' => $charges,
                'charge_details' => $chargeDetails,
            ];

            if ($attempt < $totalAttempts && $sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        return $snapshots;
    }

    private function extractRecurringChargeIds(array $invoices, array $charges): array
    {
        $ids = [];

        foreach ((array) ($invoices['data'] ?? []) as $invoice) {
            foreach ([
                data_get($invoice, 'charge.id'),
                data_get($invoice, 'charge_id'),
                data_get($invoice, 'charges.0.id'),
            ] as $candidate) {
                if (filled($candidate)) {
                    $ids[] = (string) $candidate;
                }
            }
        }

        foreach ((array) ($charges['data'] ?? []) as $charge) {
            if (filled($charge['id'] ?? null)) {
                $ids[] = (string) $charge['id'];
            }
        }

        return array_slice(array_values(array_unique($ids)), 0, 5);
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

    private function buildRecurringPlanPayload(string $code, int $amountCents): array
    {
        $name = mb_substr("Probe Recorrente {$code}", 0, 64);

        return [
            'name' => $name,
            'description' => 'Plano de homologacao recorrente mensal Eventovivo',
            'payment_methods' => ['credit_card'],
            'installments' => [1],
            'statement_descriptor' => (string) config('services.pagarme.statement_descriptor', 'EVENTOVIVO'),
            'currency' => 'BRL',
            'interval' => 'month',
            'interval_count' => 1,
            'billing_type' => 'prepaid',
            'items' => [[
                'name' => $name,
                'description' => 'Item mensal da probe recorrente',
                'quantity' => 1,
                'pricing_scheme' => [
                    'scheme_type' => 'unit',
                    'price' => $amountCents,
                ],
            ]],
            'metadata' => [
                'probe' => 'recurring_lifecycle',
                'probe_code' => $code,
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

    private function tokenizeCard(string $cardNumber, string $cvv): array
    {
        $config = config('services.pagarme', []);
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.pagar.me/core/v5'), '/');
        $publicKey = (string) ($config['public_key'] ?? '');

        if ($publicKey === '') {
            throw new RuntimeException('services.pagarme.public_key is required for recurring lifecycle tokenization probe.');
        }

        $payload = [
            'type' => 'card',
            'card' => [
                'number' => $cardNumber,
                'holder_name' => 'CAMILA ROCHA',
                'holder_document' => '12345678909',
                'exp_month' => 12,
                'exp_year' => 30,
                'cvv' => $cvv,
            ],
        ];

        return Http::acceptJson()
            ->asJson()
            ->timeout((int) ($config['timeout'] ?? 15))
            ->connectTimeout((int) ($config['connect_timeout'] ?? 5))
            ->post($baseUrl.'/tokens?appId='.$publicKey, $payload)
            ->throw()
            ->json() ?? [];
    }

    private function redactToken(array $token): array
    {
        if (isset($token['id'])) {
            $token['id'] = 'redacted:'.substr((string) $token['id'], -6);
        }

        return $token;
    }

    private function hookSnapshot(?string $hookId): array
    {
        if (filled($hookId)) {
            return [
                'hook_id' => $hookId,
                'hook' => $this->client->getHook($hookId),
            ];
        }

        return [
            'hooks' => $this->client->listHooks([
                'page' => 1,
                'size' => 10,
            ]),
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
