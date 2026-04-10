<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\BillingSubscriptionGatewayInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ReconcileRecurringSubscriptionAction
{
    public function __construct(
        private readonly BillingSubscriptionGatewayInterface $subscriptionGateway,
        private readonly ProjectRecurringBillingStateAction $projectRecurringBillingState,
    ) {}

    public function execute(Subscription $subscription, array $options = []): array
    {
        Log::info('billing.recurring.reconcile.requested', [
            'subscription_id' => $subscription->id,
            'organization_id' => $subscription->organization_id,
            'gateway_subscription_id' => $subscription->gateway_subscription_id,
            'page' => (int) ($options['page'] ?? 1),
            'size' => (int) ($options['size'] ?? 20),
            'with_charge_details' => (bool) ($options['with_charge_details'] ?? true),
        ]);

        if (! filled($subscription->gateway_subscription_id)) {
            throw ValidationException::withMessages([
                'subscription' => ['Nao foi possivel reconciliar a assinatura sem gateway_subscription_id.'],
            ]);
        }

        $providerKey = (string) ($subscription->gateway_provider ?: $this->subscriptionGateway->providerKey());

        if ($providerKey !== $this->subscriptionGateway->providerKey()) {
            throw ValidationException::withMessages([
                'subscription' => ['O gateway configurado na aplicacao nao corresponde ao provider da assinatura a reconciliar.'],
            ]);
        }

        $lock = Cache::lock(
            sprintf('billing:recurring-reconcile:%s:%s', $providerKey, $subscription->gateway_subscription_id),
            30,
        );

        return $lock->block(5, function () use ($subscription, $options, $providerKey) {
            $subscription = $subscription->fresh(['plan.features']) ?? $subscription;
            $page = max(1, (int) ($options['page'] ?? 1));
            $size = max(1, min(100, (int) ($options['size'] ?? 20)));
            $withChargeDetails = (bool) ($options['with_charge_details'] ?? true);
            $now = now();
            $remoteSubscription = $this->subscriptionGateway->fetchSubscription($subscription, $options);

            $summary = [
                'provider_key' => $providerKey,
                'subscription_id' => $subscription->id,
                'gateway_subscription_id' => (string) $subscription->gateway_subscription_id,
                'cycles_reconciled' => 0,
                'invoices_reconciled' => 0,
                'charges_reconciled' => 0,
                'charge_details_loaded' => 0,
                'page' => $page,
                'size' => $size,
            ];

            $subscriptionProjection = $this->projectSnapshot(
                'subscription.updated',
                $remoteSubscription,
                $subscription,
                $now,
            );

            $cyclesById = [];
            $seenCycleIds = [];
            foreach ($this->extractDataRows($this->subscriptionGateway->listCycles($subscription, [
                'page' => $page,
                'size' => $size,
            ])) as $cycleSnapshot) {
                $cycleId = (string) ($cycleSnapshot['id'] ?? '');

                if ($cycleId !== '' && isset($seenCycleIds[$cycleId])) {
                    continue;
                }

                if ($cycleId !== '') {
                    $seenCycleIds[$cycleId] = true;
                    $cyclesById[$cycleId] = $cycleSnapshot;
                }

                $this->projectSnapshot(
                    'subscription.updated',
                    array_merge($remoteSubscription, [
                        'cycle' => $cycleSnapshot,
                    ]),
                    $subscription,
                    $now,
                    ['gateway_cycle_id' => $cycleId !== '' ? $cycleId : null],
                );

                $summary['cycles_reconciled']++;
            }

            $invoiceIds = [];
            $chargeIds = [];
            foreach ($this->extractDataRows($this->subscriptionGateway->listInvoices($subscription, array_filter(array_merge([
                'page' => $page,
                'size' => $size,
                'subscription_id' => $subscription->gateway_subscription_id,
            ], $this->onlyAllowedFilters($options, [
                'status',
                'customer_id',
                'due_since',
                'due_until',
                'created_since',
                'created_until',
            ])))) ) as $invoiceSnapshot) {
                $invoiceId = (string) ($invoiceSnapshot['id'] ?? '');

                if ($invoiceId !== '' && isset($invoiceIds[$invoiceId])) {
                    continue;
                }

                if ($invoiceId !== '') {
                    $invoiceIds[$invoiceId] = true;
                }

                $cycleId = (string) (
                    data_get($invoiceSnapshot, 'cycle.id')
                    ?? data_get($invoiceSnapshot, 'cycle_id')
                    ?? ''
                );
                $invoicePayload = $this->ensureSubscriptionContext($invoiceSnapshot, $remoteSubscription, $subscription);

                if ($cycleId !== '' && ! isset($invoicePayload['cycle']) && isset($cyclesById[$cycleId])) {
                    $invoicePayload['cycle'] = $cyclesById[$cycleId];
                }

                $this->projectSnapshot(
                    $this->mapInvoiceEventType((string) ($invoiceSnapshot['status'] ?? 'pending')),
                    $invoicePayload,
                    $subscription,
                    $now,
                    [
                        'gateway_invoice_id' => $invoiceId !== '' ? $invoiceId : null,
                        'gateway_cycle_id' => $cycleId !== '' ? $cycleId : null,
                    ],
                );

                foreach ($this->extractChargeIdsFromInvoice($invoiceSnapshot) as $chargeId) {
                    $chargeIds[$chargeId] = true;
                }

                $summary['invoices_reconciled']++;
            }

            $listedCharges = $this->extractDataRows($this->subscriptionGateway->listCharges($subscription, array_filter(array_merge([
                'page' => $page,
                'size' => $size,
            ], $this->onlyAllowedFilters($options, [
                'status',
                'payment_method',
                'customer_id',
                'order_id',
                'created_since',
                'created_until',
            ])))));
            foreach ($listedCharges as $chargeSnapshot) {
                $chargeId = (string) ($chargeSnapshot['id'] ?? '');

                if ($chargeId !== '') {
                    $chargeIds[$chargeId] = true;
                }
            }

            $seenChargeIds = [];
            foreach (array_keys($chargeIds) as $chargeId) {
                if ($chargeId === '' || isset($seenChargeIds[$chargeId])) {
                    continue;
                }

                $seenChargeIds[$chargeId] = true;
                $chargeSnapshot = collect($listedCharges)->firstWhere('id', $chargeId);

                if ($withChargeDetails) {
                    $chargeSnapshot = $this->subscriptionGateway->getCharge($subscription, $chargeId, $options);
                    $summary['charge_details_loaded']++;
                }

                if (! is_array($chargeSnapshot) || ! $this->chargeBelongsToSubscription($chargeSnapshot, $subscription, array_keys($invoiceIds))) {
                    continue;
                }

                $chargePayload = $this->ensureSubscriptionContext($chargeSnapshot, $remoteSubscription, $subscription);
                $invoiceId = (string) (
                    data_get($chargePayload, 'invoice.id')
                    ?? data_get($chargePayload, 'invoice_id')
                    ?? ''
                );

                $this->projectSnapshot(
                    $this->mapChargeEventType((string) ($chargePayload['status'] ?? 'pending')),
                    $chargePayload,
                    $subscription,
                    $now,
                    [
                        'gateway_charge_id' => $chargeId,
                        'gateway_invoice_id' => $invoiceId !== '' ? $invoiceId : null,
                    ],
                );

                $summary['charges_reconciled']++;
            }

            $result = array_merge($summary, [
                'subscription_projection' => $subscriptionProjection,
                'subscription' => $subscription->fresh(['plan.features']),
            ]);

            Log::info('billing.recurring.reconcile.completed', [
                'subscription_id' => $subscription->id,
                'organization_id' => $subscription->organization_id,
                'gateway_subscription_id' => $subscription->gateway_subscription_id,
                'cycles_reconciled' => $result['cycles_reconciled'],
                'invoices_reconciled' => $result['invoices_reconciled'],
                'charges_reconciled' => $result['charges_reconciled'],
                'charge_details_loaded' => $result['charge_details_loaded'],
            ]);

            return $result;
        });
    }

    private function projectSnapshot(
        string $eventType,
        array $data,
        Subscription $subscription,
        Carbon $occurredAt,
        array $overrides = [],
    ): array {
        return $this->projectRecurringBillingState->execute(array_filter(array_merge([
            'provider_key' => $subscription->gateway_provider ?: $this->subscriptionGateway->providerKey(),
            'event_type' => $eventType,
            'gateway_subscription_id' => $subscription->gateway_subscription_id,
            'gateway_customer_id' => $subscription->gateway_customer_id,
            'occurred_at' => $occurredAt,
            'payload' => [
                'id' => sprintf('reconcile-%s-%s', $eventType, $subscription->id),
                'type' => $eventType,
                'created_at' => $occurredAt->toISOString(),
                'data' => $data,
            ],
        ], $overrides), fn (mixed $value): bool => $value !== null));
    }

    private function extractDataRows(array $response): array
    {
        $data = $response['data'] ?? $response;

        return is_array($data) ? array_values($data) : [];
    }

    private function extractChargeIdsFromInvoice(array $invoiceSnapshot): array
    {
        $candidates = [
            data_get($invoiceSnapshot, 'charge.id'),
            data_get($invoiceSnapshot, 'charge_id'),
            data_get($invoiceSnapshot, 'charges.0.id'),
        ];

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $candidate): ?string => filled($candidate) ? (string) $candidate : null,
            $candidates
        ))));
    }

    private function mapInvoiceEventType(string $status): string
    {
        return match (strtolower($status)) {
            'paid' => 'invoice.paid',
            'failed' => 'invoice.payment_failed',
            'canceled', 'cancelled' => 'invoice.canceled',
            default => 'invoice.created',
        };
    }

    private function mapChargeEventType(string $status): string
    {
        return match (strtolower($status)) {
            'paid' => 'charge.paid',
            'failed', 'refused', 'not_authorized' => 'charge.payment_failed',
            'refunded', 'partial_canceled', 'partially_refunded' => 'charge.refunded',
            'chargedback' => 'charge.chargedback',
            default => 'charge.pending',
        };
    }

    private function ensureSubscriptionContext(array $payload, array $remoteSubscription, Subscription $subscription): array
    {
        if (! isset($payload['subscription']) && ! isset($payload['subscription_id'])) {
            $payload['subscription'] = array_filter([
                'id' => $remoteSubscription['id'] ?? $subscription->gateway_subscription_id,
                'status' => $remoteSubscription['status'] ?? $subscription->contract_status,
                'payment_method' => $remoteSubscription['payment_method'] ?? $subscription->payment_method,
                'customer' => data_get($remoteSubscription, 'customer'),
                'customer_id' => data_get($remoteSubscription, 'customer.id')
                    ?? data_get($remoteSubscription, 'customer_id')
                    ?? $subscription->gateway_customer_id,
                'card' => data_get($remoteSubscription, 'card'),
            ], fn (mixed $value): bool => $value !== null && $value !== '');
        }

        if (! isset($payload['customer_id']) && filled($subscription->gateway_customer_id)) {
            $payload['customer_id'] = $subscription->gateway_customer_id;
        }

        return $payload;
    }

    private function chargeBelongsToSubscription(array $charge, Subscription $subscription, array $invoiceIds): bool
    {
        $subscriptionId = (string) (
            data_get($charge, 'subscription.id')
            ?? data_get($charge, 'subscription_id')
            ?? data_get($charge, 'invoice.subscription.id')
            ?? data_get($charge, 'invoice.subscription_id')
            ?? ''
        );

        if ($subscriptionId !== '' && $subscriptionId === (string) $subscription->gateway_subscription_id) {
            return true;
        }

        $invoiceId = (string) (
            data_get($charge, 'invoice.id')
            ?? data_get($charge, 'invoice_id')
            ?? ''
        );

        if ($invoiceId !== '' && in_array($invoiceId, $invoiceIds, true)) {
            return true;
        }

        return false;
    }

    private function onlyAllowedFilters(array $options, array $allowedKeys): array
    {
        return array_filter(
            array_intersect_key($options, array_flip($allowedKeys)),
            fn (mixed $value): bool => $value !== null && $value !== ''
        );
    }
}
