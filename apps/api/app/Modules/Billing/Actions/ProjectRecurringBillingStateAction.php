<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\BillingProfile;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionCycle;
use App\Modules\Billing\Services\Pagarme\PagarmeStatusMapper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectRecurringBillingStateAction
{
    public function __construct(
        private readonly PagarmeStatusMapper $statusMapper,
    ) {}

    public function execute(array $normalized, ?BillingOrder $order = null): array
    {
        $subscription = $this->resolveSubscription($normalized, $order);

        if (! $subscription) {
            throw ValidationException::withMessages([
                'subscription' => ['Nao foi possivel localizar a assinatura recorrente associada ao evento do gateway.'],
            ]);
        }

        return DB::transaction(function () use ($normalized, $order, $subscription) {
            /** @var Subscription $subscription */
            $subscription = Subscription::query()
                ->with(['plan.features'])
                ->lockForUpdate()
                ->findOrFail($subscription->id);

            $context = $this->extractContext($normalized);
            $cycle = $this->upsertCycle($subscription, $context);
            $invoice = $this->upsertInvoice($subscription, $cycle, $order, $context);
            $payment = $this->upsertPayment($subscription, $invoice, $order, $context);
            $this->syncBillingProfile($subscription, $context);
            $subscription = $this->projectSubscription($subscription, $context, $cycle, $invoice, $payment);

            return array_filter([
                'action' => $this->resolveAction($normalized['event_type'] ?? 'unsupported'),
                'subscription_id' => $subscription->id,
                'subscription_cycle_id' => $cycle?->id,
                'invoice_id' => $invoice?->id,
                'payment_id' => $payment?->id,
                'contract_status' => $subscription->contract_status,
                'billing_status' => $subscription->billing_status,
                'access_status' => $subscription->access_status,
            ], fn (mixed $value): bool => $value !== null);
        });
    }

    private function resolveSubscription(array $normalized, ?BillingOrder $order = null): ?Subscription
    {
        if (filled($normalized['gateway_subscription_id'] ?? null)) {
            $subscription = Subscription::query()
                ->where('gateway_provider', $normalized['provider_key'] ?? 'pagarme')
                ->where('gateway_subscription_id', $normalized['gateway_subscription_id'])
                ->first();

            if ($subscription) {
                return $subscription;
            }
        }

        if ($order?->organization_id) {
            return Subscription::query()
                ->where('organization_id', $order->organization_id)
                ->latest('id')
                ->first();
        }

        if (filled($normalized['gateway_customer_id'] ?? null)) {
            return Subscription::query()
                ->where('gateway_provider', $normalized['provider_key'] ?? 'pagarme')
                ->where('gateway_customer_id', $normalized['gateway_customer_id'])
                ->latest('id')
                ->first();
        }

        return null;
    }

    private function extractContext(array $normalized): array
    {
        $payload = (array) ($normalized['payload'] ?? []);
        $gatewayData = (array) ($payload['data'] ?? []);
        $rawEventType = (string) ($payload['type'] ?? $normalized['event_type'] ?? '');

        $subscriptionSnapshot = str_starts_with($rawEventType, 'subscription.')
            ? $gatewayData
            : (array) (data_get($gatewayData, 'subscription') ?? []);
        $invoiceSnapshot = str_starts_with($rawEventType, 'invoice.')
            ? $gatewayData
            : (array) (data_get($gatewayData, 'invoice') ?? []);
        $chargeSnapshot = str_starts_with($rawEventType, 'charge.')
            ? $gatewayData
            : (array) (data_get($gatewayData, 'charge') ?? []);
        $cycleSnapshot = (array) (data_get($gatewayData, 'cycle')
            ?? data_get($invoiceSnapshot, 'cycle')
            ?? []);
        $lastTransaction = (array) (data_get($chargeSnapshot, 'last_transaction') ?? []);

        $gatewayChargeId = $normalized['gateway_charge_id']
            ?? data_get($chargeSnapshot, 'id')
            ?? data_get($invoiceSnapshot, 'charge.id')
            ?? data_get($invoiceSnapshot, 'charge_id');
        $gatewayInvoiceId = $normalized['gateway_invoice_id']
            ?? data_get($invoiceSnapshot, 'id');
        $gatewayCycleId = $normalized['gateway_cycle_id']
            ?? data_get($cycleSnapshot, 'id');
        $gatewaySubscriptionId = $normalized['gateway_subscription_id']
            ?? data_get($subscriptionSnapshot, 'id')
            ?? data_get($invoiceSnapshot, 'subscription.id')
            ?? data_get($invoiceSnapshot, 'subscription_id')
            ?? data_get($chargeSnapshot, 'subscription.id')
            ?? data_get($chargeSnapshot, 'subscription_id');

        $invoiceStatus = data_get($invoiceSnapshot, 'status');
        $chargeStatus = data_get($chargeSnapshot, 'status');
        $subscriptionStatus = data_get($subscriptionSnapshot, 'status');
        $normalizedChargeStatus = is_scalar($chargeStatus) ? strtolower((string) $chargeStatus) : null;
        $normalizedInvoiceStatus = is_scalar($invoiceStatus)
            ? strtolower((string) $invoiceStatus)
            : match ($normalizedChargeStatus) {
                'paid' => 'paid',
                'failed', 'refused', 'not_authorized', 'chargedback' => 'failed',
                'pending', 'processing', 'waiting_payment' => 'pending',
                'refunded', 'partial_canceled', 'partially_refunded' => 'refunded',
                default => null,
            };

        return [
            'raw_event_type' => $rawEventType,
            'event_type' => (string) ($normalized['event_type'] ?? 'unsupported'),
            'provider_key' => $normalized['provider_key'] ?? 'pagarme',
            'occurred_at' => $normalized['occurred_at'] ?? now(),
            'gateway_order_id' => $normalized['gateway_order_id'] ?? null,
            'gateway_subscription_id' => $gatewaySubscriptionId,
            'gateway_invoice_id' => $gatewayInvoiceId,
            'gateway_cycle_id' => $gatewayCycleId,
            'gateway_customer_id' => $normalized['gateway_customer_id']
                ?? data_get($subscriptionSnapshot, 'customer.id')
                ?? data_get($subscriptionSnapshot, 'customer_id')
                ?? data_get($chargeSnapshot, 'customer.id')
                ?? data_get($chargeSnapshot, 'customer_id'),
            'gateway_charge_id' => $gatewayChargeId,
            'gateway_transaction_id' => $normalized['gateway_transaction_id']
                ?? data_get($lastTransaction, 'id')
                ?? data_get($lastTransaction, 'transaction_id'),
            'subscription_snapshot' => $subscriptionSnapshot,
            'invoice_snapshot' => $invoiceSnapshot,
            'charge_snapshot' => $chargeSnapshot,
            'cycle_snapshot' => $cycleSnapshot,
            'last_transaction' => $lastTransaction ?: null,
            'subscription_status' => is_scalar($subscriptionStatus) ? strtolower((string) $subscriptionStatus) : null,
            'invoice_status' => $normalizedInvoiceStatus,
            'charge_status' => $normalizedChargeStatus,
            'invoice_number' => data_get($invoiceSnapshot, 'code')
                ?? data_get($invoiceSnapshot, 'invoice_number'),
            'amount_cents' => $this->extractAmountCents($chargeSnapshot, $invoiceSnapshot),
            'currency' => strtoupper((string) (
                data_get($invoiceSnapshot, 'currency')
                ?? data_get($chargeSnapshot, 'currency')
                ?? 'BRL'
            )),
            'payment_method' => data_get($chargeSnapshot, 'payment_method')
                ?? data_get($subscriptionSnapshot, 'payment_method')
                ?? 'credit_card',
            'card_brand' => data_get($chargeSnapshot, 'card.brand')
                ?? data_get($chargeSnapshot, 'card.brand_name')
                ?? data_get($lastTransaction, 'card.brand')
                ?? data_get($lastTransaction, 'card.brand_name'),
            'card_last_four' => data_get($chargeSnapshot, 'card.last_four_digits')
                ?? data_get($chargeSnapshot, 'card.last_four')
                ?? data_get($lastTransaction, 'card.last_four_digits')
                ?? data_get($lastTransaction, 'card.last_four'),
            'acquirer_return_code' => data_get($lastTransaction, 'acquirer_return_code'),
            'acquirer_message' => data_get($lastTransaction, 'acquirer_message'),
            'invoice_issued_at' => $this->parseDate(data_get($invoiceSnapshot, 'created_at') ?? data_get($invoiceSnapshot, 'issued_at')),
            'invoice_due_at' => $this->parseDate(data_get($invoiceSnapshot, 'due_at') ?? data_get($invoiceSnapshot, 'billing_at')),
            'invoice_paid_at' => $this->parseDate(data_get($invoiceSnapshot, 'paid_at') ?? data_get($chargeSnapshot, 'paid_at')),
            'billing_at' => $this->parseDate(data_get($cycleSnapshot, 'billing_at') ?? data_get($invoiceSnapshot, 'billing_at')),
            'period_start_at' => $this->parseDate(
                data_get($cycleSnapshot, 'start_at')
                ?? data_get($invoiceSnapshot, 'period.start_at')
                ?? data_get($invoiceSnapshot, 'start_at')
            ),
            'period_end_at' => $this->parseDate(
                data_get($cycleSnapshot, 'end_at')
                ?? data_get($invoiceSnapshot, 'period.end_at')
                ?? data_get($invoiceSnapshot, 'end_at')
            ),
            'cycle_closed_at' => $this->parseDate(data_get($cycleSnapshot, 'closed_at')),
            'subscription_starts_at' => $this->parseDate(data_get($subscriptionSnapshot, 'start_at')),
            'subscription_next_billing_at' => $this->parseDate(data_get($subscriptionSnapshot, 'next_billing_at')),
            'subscription_current_period_start_at' => $this->parseDate(
                data_get($subscriptionSnapshot, 'current_period.start_at')
                ?? data_get($subscriptionSnapshot, 'current_period_started_at')
            ),
            'subscription_current_period_end_at' => $this->parseDate(
                data_get($subscriptionSnapshot, 'current_period.end_at')
                ?? data_get($subscriptionSnapshot, 'current_period_end_at')
            ),
            'subscription_canceled_at' => $this->parseDate(data_get($subscriptionSnapshot, 'canceled_at')),
            'subscription_status_reason' => data_get($subscriptionSnapshot, 'status_reason'),
            'subscription_card_id' => data_get($subscriptionSnapshot, 'card.id')
                ?? data_get($chargeSnapshot, 'card.id'),
            'raw_payload' => $payload,
        ];
    }

    private function upsertCycle(Subscription $subscription, array $context): ?SubscriptionCycle
    {
        if (! filled($context['gateway_cycle_id']) && ! $context['period_start_at'] && ! $context['period_end_at'] && ! $context['billing_at']) {
            return null;
        }

        if (filled($context['gateway_cycle_id'])) {
            $cycle = SubscriptionCycle::query()->firstOrNew([
                'gateway_cycle_id' => (string) $context['gateway_cycle_id'],
            ]);
        } else {
            $query = SubscriptionCycle::query()->where('subscription_id', $subscription->id);

            if ($context['period_end_at']) {
                $query->where('period_end_at', $context['period_end_at']);
            } elseif ($context['billing_at']) {
                $query->where('billing_at', $context['billing_at']);
            } else {
                $query->where('period_start_at', $context['period_start_at']);
            }

            $cycle = $query->first() ?? new SubscriptionCycle([
                'subscription_id' => $subscription->id,
            ]);
        }

        $cycle->fill([
            'subscription_id' => $subscription->id,
            'gateway_cycle_id' => $context['gateway_cycle_id'] ?: $cycle->gateway_cycle_id,
            'status' => strtolower((string) (data_get($context, 'cycle_snapshot.status') ?? $context['invoice_status'] ?? 'pending')),
            'billing_at' => $context['billing_at'] ?? $cycle->billing_at,
            'period_start_at' => $context['period_start_at'] ?? $cycle->period_start_at,
            'period_end_at' => $context['period_end_at'] ?? $cycle->period_end_at,
            'closed_at' => $context['cycle_closed_at'] ?? $cycle->closed_at,
            'raw_gateway_json' => data_get($context, 'cycle_snapshot') ?: $cycle->raw_gateway_json,
        ]);
        $cycle->save();

        return $cycle->fresh();
    }

    private function upsertInvoice(
        Subscription $subscription,
        ?SubscriptionCycle $cycle,
        ?BillingOrder $order,
        array $context,
    ): ?Invoice {
        if (! filled($context['gateway_invoice_id']) && ! filled($context['invoice_status'])) {
            return null;
        }

        if (filled($context['gateway_invoice_id'])) {
            $invoice = Invoice::query()->firstOrNew([
                'gateway_invoice_id' => (string) $context['gateway_invoice_id'],
            ]);
        } else {
            $query = Invoice::query()->where('subscription_id', $subscription->id);

            if ($cycle?->id) {
                $query->where('subscription_cycle_id', $cycle->id);
            }

            if (filled($context['invoice_number'])) {
                $query->where('invoice_number', (string) $context['invoice_number']);
            } elseif (filled($context['gateway_charge_id'])) {
                $query->where('gateway_charge_id', (string) $context['gateway_charge_id']);
            } else {
                $query->where('period_end_at', $context['period_end_at']);
            }

            $invoice = $query->first() ?? new Invoice([
                'organization_id' => $subscription->organization_id,
                'subscription_id' => $subscription->id,
            ]);
        }

        $invoice->fill([
            'organization_id' => $subscription->organization_id,
            'billing_order_id' => $invoice->billing_order_id ?? $order?->id,
            'subscription_id' => $subscription->id,
            'subscription_cycle_id' => $cycle?->id,
            'gateway_invoice_id' => $context['gateway_invoice_id'] ?: $invoice->gateway_invoice_id,
            'gateway_charge_id' => $context['gateway_charge_id'] ?: $invoice->gateway_charge_id,
            'gateway_cycle_id' => $context['gateway_cycle_id'] ?: $invoice->gateway_cycle_id,
            'invoice_number' => $context['invoice_number'] ?: $invoice->invoice_number,
            'status' => $this->statusMapper->toInvoiceStatus($context['invoice_status']),
            'gateway_status' => $context['invoice_status'] ?? $invoice->gateway_status,
            'amount_cents' => $context['amount_cents'] ?? $invoice->amount_cents ?? 0,
            'currency' => $context['currency'] ?? $invoice->currency ?? 'BRL',
            'issued_at' => $context['invoice_issued_at'] ?? $invoice->issued_at,
            'due_at' => $context['invoice_due_at'] ?? $invoice->due_at,
            'paid_at' => $context['invoice_paid_at'] ?? (
                in_array($context['invoice_status'], ['paid'], true)
                    ? $context['occurred_at']
                    : $invoice->paid_at
            ),
            'period_start_at' => $context['period_start_at'] ?? $invoice->period_start_at,
            'period_end_at' => $context['period_end_at'] ?? $invoice->period_end_at,
            'snapshot_json' => $this->buildInvoiceSnapshot($subscription),
            'raw_gateway_json' => data_get($context, 'invoice_snapshot') ?: $invoice->raw_gateway_json,
        ]);
        $invoice->save();

        return $invoice->fresh();
    }

    private function upsertPayment(
        Subscription $subscription,
        ?Invoice $invoice,
        ?BillingOrder $order,
        array $context,
    ): ?Payment {
        if (! filled($context['gateway_charge_id']) && ! filled($context['charge_status'])) {
            return null;
        }

        if (filled($context['gateway_charge_id'])) {
            $payment = Payment::query()->firstOrNew([
                'gateway_provider' => $context['provider_key'],
                'gateway_charge_id' => (string) $context['gateway_charge_id'],
            ]);
        } else {
            if (! $invoice) {
                return null;
            }

            $payment = Payment::query()->firstOrNew([
                'invoice_id' => $invoice->id,
                'attempt_sequence' => (int) ($context['attempt_sequence'] ?? 1),
            ]);
        }

        $payment->fill([
            'billing_order_id' => $payment->billing_order_id ?? $order?->id,
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice?->id,
            'status' => $this->statusMapper->toPaymentStatus($context['charge_status']),
            'amount_cents' => $context['amount_cents'] ?? $payment->amount_cents ?? 0,
            'currency' => $context['currency'] ?? $payment->currency ?? 'BRL',
            'payment_method' => $context['payment_method'] ?? $payment->payment_method ?? 'credit_card',
            'gateway_provider' => $context['provider_key'],
            'gateway_payment_id' => $context['gateway_charge_id'] ?? $payment->gateway_payment_id,
            'gateway_order_id' => $context['gateway_order_id'] ?? $payment->gateway_order_id,
            'gateway_charge_id' => $context['gateway_charge_id'] ?? $payment->gateway_charge_id,
            'gateway_invoice_id' => $context['gateway_invoice_id'] ?? $payment->gateway_invoice_id,
            'gateway_transaction_id' => $context['gateway_transaction_id'] ?? $payment->gateway_transaction_id,
            'gateway_status' => $context['charge_status'] ?? $payment->gateway_status,
            'gateway_charge_status' => $context['charge_status'] ?? $payment->gateway_charge_status,
            'paid_at' => $this->statusMapper->toPaymentStatus($context['charge_status']) === 'paid'
                ? ($context['invoice_paid_at'] ?? $context['occurred_at'])
                : $payment->paid_at,
            'failed_at' => $this->statusMapper->toPaymentStatus($context['charge_status']) === 'failed'
                ? ($context['occurred_at'] ?? $payment->failed_at)
                : $payment->failed_at,
            'refunded_at' => in_array($this->statusMapper->toPaymentStatus($context['charge_status']), ['refunded', 'chargedback'], true)
                ? ($context['occurred_at'] ?? $payment->refunded_at)
                : $payment->refunded_at,
            'card_brand' => $context['card_brand'] ?? $payment->card_brand,
            'card_last_four' => $context['card_last_four'] ?? $payment->card_last_four,
            'attempt_sequence' => $payment->attempt_sequence ?? 1,
            'last_transaction_json' => $context['last_transaction'] ?? $payment->last_transaction_json,
            'gateway_response_json' => data_get($context, 'charge_snapshot') ?: $payment->gateway_response_json,
            'acquirer_return_code' => $context['acquirer_return_code'] ?? $payment->acquirer_return_code,
            'acquirer_message' => $context['acquirer_message'] ?? $payment->acquirer_message,
            'raw_payload_json' => data_get($context, 'charge_snapshot') ?: $payment->raw_payload_json,
        ]);
        $payment->save();

        return $payment->fresh();
    }

    private function syncBillingProfile(Subscription $subscription, array $context): void
    {
        if (! filled($context['gateway_customer_id']) && ! filled($context['subscription_card_id'])) {
            return;
        }

        BillingProfile::query()->updateOrCreate(
            ['organization_id' => $subscription->organization_id],
            array_filter([
                'gateway_provider' => $subscription->gateway_provider ?: ($context['provider_key'] ?? 'pagarme'),
                'gateway_customer_id' => $context['gateway_customer_id'] ?? null,
                'gateway_default_card_id' => $context['subscription_card_id'] ?? null,
            ], fn (mixed $value): bool => $value !== null && $value !== '')
        );
    }

    private function projectSubscription(
        Subscription $subscription,
        array $context,
        ?SubscriptionCycle $cycle,
        ?Invoice $invoice,
        ?Payment $payment,
    ): Subscription {
        $contractStatus = $this->statusMapper->toRecurringContractStatus(
            $context['subscription_status'] ?? $subscription->contract_status,
            $context['charge_status']
        );
        $billingStatus = $this->statusMapper->toRecurringBillingStatus(
            $context['invoice_status'] ?? $invoice?->gateway_status ?? $subscription->billing_status,
            $context['charge_status'] ?? $payment?->gateway_charge_status
        );

        $currentPeriodEnd = $context['subscription_current_period_end_at']
            ?? $context['period_end_at']
            ?? $cycle?->period_end_at
            ?? $subscription->current_period_ends_at;
        $canceledAt = $context['subscription_canceled_at'];
        $hasFutureAccess = $currentPeriodEnd instanceof Carbon && $currentPeriodEnd->isFuture();
        $accessStatus = $this->statusMapper->toRecurringAccessStatus($contractStatus, $billingStatus, $hasFutureAccess);

        $metadata = array_merge($subscription->metadata_json ?? [], array_filter([
            'gateway_status' => $context['subscription_status'] ?? null,
            'gateway_invoice_id' => $context['gateway_invoice_id'] ?? null,
            'gateway_charge_id' => $context['gateway_charge_id'] ?? null,
            'gateway_cycle_id' => $context['gateway_cycle_id'] ?? null,
            'last_recurring_event_type' => $context['event_type'] ?? null,
            'last_recurring_event_at' => $context['occurred_at'] instanceof Carbon ? $context['occurred_at']->toISOString() : null,
        ], fn (mixed $value): bool => $value !== null && $value !== ''));

        $subscription->forceFill([
            'status' => $contractStatus,
            'payment_method' => $context['payment_method'] ?? $subscription->payment_method,
            'starts_at' => $context['subscription_starts_at'] ?? $subscription->starts_at,
            'current_period_started_at' => $context['subscription_current_period_start_at']
                ?? $context['period_start_at']
                ?? $cycle?->period_start_at
                ?? $subscription->current_period_started_at,
            'current_period_ends_at' => $currentPeriodEnd,
            'renews_at' => $context['subscription_next_billing_at']
                ?? $currentPeriodEnd
                ?? $subscription->renews_at,
            'next_billing_at' => $context['subscription_next_billing_at']
                ?? $cycle?->billing_at
                ?? $subscription->next_billing_at,
            'ends_at' => $contractStatus === 'canceled'
                ? ($currentPeriodEnd ?? $subscription->ends_at ?? ($context['occurred_at'] instanceof Carbon ? $context['occurred_at'] : now()))
                : null,
            'canceled_at' => $contractStatus === 'canceled'
                ? ($canceledAt ?? ($subscription->canceled_at ?: ($context['occurred_at'] instanceof Carbon ? $context['occurred_at'] : now())))
                : null,
            'gateway_provider' => $context['provider_key'] ?? $subscription->gateway_provider,
            'gateway_customer_id' => $context['gateway_customer_id'] ?? $subscription->gateway_customer_id,
            'gateway_card_id' => $context['subscription_card_id'] ?? $subscription->gateway_card_id,
            'gateway_status_reason' => $context['subscription_status_reason'] ?? $subscription->gateway_status_reason,
            'contract_status' => $contractStatus,
            'billing_status' => $billingStatus,
            'access_status' => $accessStatus,
            'gateway_subscription_id' => $context['gateway_subscription_id'] ?? $subscription->gateway_subscription_id,
            'metadata_json' => $metadata,
        ])->save();

        return $subscription->fresh(['plan.features']);
    }

    private function buildInvoiceSnapshot(Subscription $subscription): array
    {
        $subscription->loadMissing(['plan']);

        return array_filter([
            'plan' => $subscription->plan ? [
                'id' => $subscription->plan->id,
                'code' => $subscription->plan->code,
                'name' => $subscription->plan->name,
                'audience' => $subscription->plan->audience,
                'description' => $subscription->plan->description,
            ] : null,
            'subscription' => [
                'id' => $subscription->id,
                'gateway_subscription_id' => $subscription->gateway_subscription_id,
                'billing_cycle' => $subscription->billing_cycle,
            ],
        ], fn (mixed $value): bool => $value !== null);
    }

    private function resolveAction(string $eventType): string
    {
        return match ($eventType) {
            'subscription.created', 'subscription.updated', 'subscription.canceled' => 'subscription_projected',
            'invoice.created' => 'invoice_projected',
            'invoice.paid' => 'invoice_paid',
            'invoice.payment_failed' => 'invoice_payment_failed',
            'invoice.canceled' => 'invoice_canceled',
            'charge.pending', 'charge.paid', 'charge.payment_failed', 'charge.refunded', 'charge.chargedback' => 'charge_projected',
            default => 'recurring_projection_ignored',
        };
    }

    private function extractAmountCents(array $chargeSnapshot, array $invoiceSnapshot): ?int
    {
        foreach ([
            data_get($chargeSnapshot, 'amount'),
            data_get($chargeSnapshot, 'amount_cents'),
            data_get($invoiceSnapshot, 'amount'),
            data_get($invoiceSnapshot, 'amount_cents'),
            data_get($invoiceSnapshot, 'total'),
        ] as $candidate) {
            if (is_numeric($candidate)) {
                return (int) $candidate;
            }
        }

        return null;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (! filled($value)) {
            return null;
        }

        return Carbon::parse((string) $value);
    }
}
