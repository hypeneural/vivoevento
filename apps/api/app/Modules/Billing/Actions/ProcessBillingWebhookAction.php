<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\BillingGatewayEvent;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Services\BillingGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProcessBillingWebhookAction
{
    public function __construct(
        private readonly BillingGatewayManager $gatewayManager,
        private readonly RecordBillingGatewayWebhookAction $recordBillingGatewayWebhook,
        private readonly RegisterBillingGatewayPaymentAction $registerBillingGatewayPayment,
        private readonly FailBillingOrderAction $failBillingOrder,
        private readonly RefundBillingOrderAction $refundBillingOrder,
        private readonly CancelBillingOrderAction $cancelBillingOrder,
        private readonly ProjectRecurringBillingStateAction $projectRecurringBillingState,
    ) {}

    public function execute(string $provider, array $payload, array $headers = []): array
    {
        $gateway = $this->gatewayManager->forProvider($provider);
        $normalized = $gateway->parseWebhook($payload, $headers);
        $gatewayEvent = $this->recordBillingGatewayWebhook->execute($normalized);

        return $this->executeRecorded($gatewayEvent);
    }

    public function executeRecorded(BillingGatewayEvent|int $gatewayEvent): array
    {
        $gatewayEvent = $gatewayEvent instanceof BillingGatewayEvent
            ? $gatewayEvent->fresh()
            : BillingGatewayEvent::query()->find($gatewayEvent);

        if (! $gatewayEvent) {
            return [
                'duplicate' => true,
                'gateway_event' => null,
                'result' => [
                    'action' => 'ignored',
                    'reason' => 'missing_gateway_event',
                ],
            ];
        }

        if ($gatewayEvent->status === 'processed') {
            return [
                'duplicate' => true,
                'gateway_event' => $this->serializeGatewayEvent($gatewayEvent),
                'result' => $gatewayEvent->result_json ?? [],
            ];
        }

        $gateway = $this->gatewayManager->forProvider($gatewayEvent->provider_key);
        $normalized = $gateway->parseWebhook($gatewayEvent->payload_json ?? [], $gatewayEvent->headers_json ?? []);
        $order = $this->resolveOrder($normalized, $gatewayEvent);

        try {
            $result = DB::transaction(function () use ($normalized, $order) {
                if ($this->isRecurringEventType($normalized['event_type'] ?? null)) {
                    return $this->projectRecurringBillingState->execute($normalized, $order);
                }

                return match ($normalized['event_type']) {
                    'payment.paid' => $this->handlePaid($order, $normalized),
                    'payment.failed' => $this->handleFailed($order, $normalized),
                    'checkout.canceled' => $this->handleCanceled($order, $normalized),
                    'payment.refunded', 'payment.partially_refunded', 'payment.chargeback' => $this->handleRefunded($order, $normalized),
                    default => [
                        'action' => 'ignored',
                        'reason' => 'unsupported_event_type',
                    ],
                };
            });

            $gatewayEvent->forceFill([
                'status' => ($result['action'] ?? null) === 'ignored' ? 'ignored' : 'processed',
                'billing_order_id' => $order?->id,
                'gateway_order_id' => $normalized['gateway_order_id'] ?? $gatewayEvent->gateway_order_id,
                'gateway_subscription_id' => $normalized['gateway_subscription_id'] ?? $gatewayEvent->gateway_subscription_id,
                'gateway_invoice_id' => $normalized['gateway_invoice_id'] ?? $gatewayEvent->gateway_invoice_id,
                'gateway_cycle_id' => $normalized['gateway_cycle_id'] ?? $gatewayEvent->gateway_cycle_id,
                'gateway_customer_id' => $normalized['gateway_customer_id'] ?? $gatewayEvent->gateway_customer_id,
                'gateway_charge_id' => $normalized['gateway_charge_id'] ?? $gatewayEvent->gateway_charge_id,
                'gateway_transaction_id' => $normalized['gateway_transaction_id'] ?? $gatewayEvent->gateway_transaction_id,
                'occurred_at' => $normalized['occurred_at'] ?? $gatewayEvent->occurred_at,
                'processed_at' => now(),
                'result_json' => $result,
            ])->save();

            return [
                'duplicate' => false,
                'gateway_event' => $this->serializeGatewayEvent($gatewayEvent->fresh()),
                'result' => $result,
            ];
        } catch (\Throwable $exception) {
            $gatewayEvent->forceFill([
                'status' => 'failed',
                'billing_order_id' => $order?->id,
                'gateway_order_id' => $normalized['gateway_order_id'] ?? $gatewayEvent->gateway_order_id,
                'gateway_subscription_id' => $normalized['gateway_subscription_id'] ?? $gatewayEvent->gateway_subscription_id,
                'gateway_invoice_id' => $normalized['gateway_invoice_id'] ?? $gatewayEvent->gateway_invoice_id,
                'gateway_cycle_id' => $normalized['gateway_cycle_id'] ?? $gatewayEvent->gateway_cycle_id,
                'gateway_customer_id' => $normalized['gateway_customer_id'] ?? $gatewayEvent->gateway_customer_id,
                'gateway_charge_id' => $normalized['gateway_charge_id'] ?? $gatewayEvent->gateway_charge_id,
                'gateway_transaction_id' => $normalized['gateway_transaction_id'] ?? $gatewayEvent->gateway_transaction_id,
                'result_json' => [
                    'action' => 'failed',
                    'message' => $exception->getMessage(),
                ],
            ])->save();

            throw $exception;
        }
    }

    private function resolveOrder(array $normalized, BillingGatewayEvent $gatewayEvent): ?BillingOrder
    {
        if ($gatewayEvent->billing_order_id) {
            return BillingOrder::query()->find($gatewayEvent->billing_order_id);
        }

        if (! empty($normalized['billing_order_uuid'])) {
            return BillingOrder::query()
                ->where('uuid', $normalized['billing_order_uuid'])
                ->first();
        }

        if (! empty($normalized['gateway_order_id'])) {
            return BillingOrder::query()
                ->where('gateway_order_id', $normalized['gateway_order_id'])
                ->first();
        }

        return null;
    }

    private function handlePaid(?BillingOrder $order, array $normalized): array
    {
        if (! $order) {
            throw ValidationException::withMessages([
                'billing_order' => ['Nao foi possivel localizar o pedido associado ao webhook de pagamento.'],
            ]);
        }

        $details = $this->extractGatewayDetails($normalized);

        $result = $this->registerBillingGatewayPayment->execute($order, [
            'gateway_provider' => $normalized['provider_key'],
            'gateway_order_id' => $details['gateway_order_id'] ?? $normalized['gateway_order_id'] ?? $order->gateway_order_id,
            'gateway_payment_id' => $details['gateway_charge_id'] ?? $normalized['gateway_payment_id'] ?? $normalized['gateway_order_id'] ?? $order->gateway_order_id,
            'gateway_charge_id' => $details['gateway_charge_id'] ?? $normalized['gateway_charge_id'] ?? $order->gateway_charge_id,
            'gateway_transaction_id' => $details['gateway_transaction_id'] ?? $normalized['gateway_transaction_id'] ?? $order->gateway_transaction_id,
            'gateway_status' => $details['gateway_status'] ?? 'paid',
            'payment_status' => $details['payment_status'] ?? 'paid',
            'paid_at' => $normalized['occurred_at'] ?? now(),
            'payment_payload' => $normalized['payload'] ?? [],
            'gateway_response' => $normalized['payload'] ?? [],
            'last_transaction' => $details['last_transaction'] ?? null,
            'acquirer_return_code' => $details['acquirer_return_code'] ?? null,
            'acquirer_message' => $details['acquirer_message'] ?? null,
            'qr_code' => $details['qr_code'] ?? null,
            'qr_code_url' => $details['qr_code_url'] ?? null,
            'expires_at' => $details['expires_at'] ?? null,
        ]);

        return array_filter([
            'action' => 'payment_registered',
            'billing_order_id' => $result['order']->id ?? $order->id,
            'payment_id' => $result['payment']->id ?? null,
            'invoice_id' => $result['invoice']->id ?? null,
            'subscription_id' => $result['subscription']->id ?? null,
            'purchase_id' => $result['purchase']->id ?? null,
            'event_id' => $result['event']->id ?? null,
        ], fn ($value) => $value !== null);
    }

    private function handleFailed(?BillingOrder $order, array $normalized): array
    {
        if (! $order) {
            throw ValidationException::withMessages([
                'billing_order' => ['Nao foi possivel localizar o pedido associado ao webhook de falha.'],
            ]);
        }

        $details = $this->extractGatewayDetails($normalized);
        $result = $this->failBillingOrder->execute($order, [
            'gateway_provider' => $normalized['provider_key'],
            'gateway_order_id' => $details['gateway_order_id'] ?? $normalized['gateway_order_id'] ?? $order->gateway_order_id,
            'gateway_payment_id' => $details['gateway_charge_id'] ?? $normalized['gateway_payment_id'] ?? $order->gateway_charge_id ?? $order->gateway_order_id,
            'gateway_charge_id' => $details['gateway_charge_id'] ?? $normalized['gateway_charge_id'] ?? $order->gateway_charge_id,
            'gateway_transaction_id' => $details['gateway_transaction_id'] ?? $normalized['gateway_transaction_id'] ?? $order->gateway_transaction_id,
            'gateway_status' => $details['gateway_status'] ?? 'failed',
            'payment_status' => $details['payment_status'] ?? 'failed',
            'failed_at' => $normalized['occurred_at'] ?? now(),
            'payment_payload' => $normalized['payload'] ?? [],
            'gateway_response' => $normalized['payload'] ?? [],
            'last_transaction' => $details['last_transaction'] ?? null,
            'acquirer_return_code' => $details['acquirer_return_code'] ?? null,
            'acquirer_message' => $details['acquirer_message'] ?? null,
        ]);

        return [
            'action' => 'payment_failed',
            'billing_order_id' => $result['order']->id,
            'payment_id' => $result['payment']->id ?? null,
            'status' => $result['order']->status?->value,
        ];
    }

    private function handleCanceled(?BillingOrder $order, array $normalized): array
    {
        if (! $order) {
            throw ValidationException::withMessages([
                'billing_order' => ['Nao foi possivel localizar o pedido associado ao webhook de cancelamento.'],
            ]);
        }

        $details = $this->extractGatewayDetails($normalized);
        $canceledOrder = $this->cancelBillingOrder->execute($order, [
            'gateway_provider' => $normalized['provider_key'],
            'gateway_order_id' => $details['gateway_order_id'] ?? $normalized['gateway_order_id'] ?? $order->gateway_order_id,
            'gateway_charge_id' => $details['gateway_charge_id'] ?? $normalized['gateway_charge_id'] ?? $order->gateway_charge_id,
            'gateway_transaction_id' => $details['gateway_transaction_id'] ?? $normalized['gateway_transaction_id'] ?? $order->gateway_transaction_id,
            'gateway_status' => $details['gateway_status'] ?? 'canceled',
            'gateway_response' => $normalized['payload'] ?? [],
            'reason' => 'gateway_webhook_canceled',
        ]);

        return [
            'action' => 'order_canceled',
            'billing_order_id' => $canceledOrder->id,
            'status' => $canceledOrder->status?->value,
        ];
    }

    private function handleRefunded(?BillingOrder $order, array $normalized): array
    {
        if (! $order) {
            throw ValidationException::withMessages([
                'billing_order' => ['Nao foi possivel localizar o pedido associado ao webhook de estorno.'],
            ]);
        }

        $details = $this->extractGatewayDetails($normalized);
        $purchaseStatus = match ($normalized['event_type']) {
            'payment.chargeback' => 'chargedback',
            default => 'refunded',
        };

        $result = $this->refundBillingOrder->execute($order, [
            'gateway_provider' => $normalized['provider_key'],
            'gateway_order_id' => $details['gateway_order_id'] ?? $normalized['gateway_order_id'] ?? $order->gateway_order_id,
            'gateway_payment_id' => $details['gateway_charge_id'] ?? $normalized['gateway_payment_id'] ?? $order->gateway_charge_id ?? $order->gateway_order_id,
            'gateway_charge_id' => $details['gateway_charge_id'] ?? $normalized['gateway_charge_id'] ?? $order->gateway_charge_id,
            'gateway_transaction_id' => $details['gateway_transaction_id'] ?? $normalized['gateway_transaction_id'] ?? $order->gateway_transaction_id,
            'gateway_status' => $details['gateway_status'] ?? 'refunded',
            'payment_status' => $details['payment_status'] ?? 'refunded',
            'refunded_at' => $normalized['occurred_at'] ?? now(),
            'payment_payload' => $normalized['payload'] ?? [],
            'gateway_response' => $normalized['payload'] ?? [],
            'last_transaction' => $details['last_transaction'] ?? null,
            'purchase_status' => $purchaseStatus,
        ]);

        return [
            'action' => 'payment_refunded',
            'billing_order_id' => $result['order']->id,
            'payment_id' => $result['payment']->id ?? null,
            'invoice_id' => $result['invoice']->id ?? null,
            'status' => $result['order']->status?->value,
        ];
    }

    private function extractGatewayDetails(array $normalized): array
    {
        $payload = (array) ($normalized['payload'] ?? []);
        $providerType = (string) ($payload['type'] ?? '');
        $gatewayData = (array) ($payload['data'] ?? []);
        $charge = Str::startsWith($providerType, 'charge.')
            ? $gatewayData
            : (array) data_get($gatewayData, 'charges.0', []);
        $lastTransaction = (array) ($charge['last_transaction'] ?? []);

        return [
            'gateway_order_id' => $normalized['gateway_order_id'] ?? data_get($gatewayData, 'id') ?? data_get($charge, 'order.id'),
            'gateway_charge_id' => $normalized['gateway_charge_id'] ?? ($charge['id'] ?? null),
            'gateway_transaction_id' => $normalized['gateway_transaction_id']
                ?? ($lastTransaction['id'] ?? data_get($lastTransaction, 'transaction_id')),
            'gateway_status' => strtolower((string) ($gatewayData['status'] ?? $charge['status'] ?? '')),
            'payment_status' => strtolower((string) ($charge['status'] ?? $gatewayData['status'] ?? '')),
            'last_transaction' => $lastTransaction ?: null,
            'acquirer_return_code' => $lastTransaction['acquirer_return_code'] ?? null,
            'acquirer_message' => $lastTransaction['acquirer_message'] ?? null,
            'qr_code' => $lastTransaction['qr_code'] ?? null,
            'qr_code_url' => $lastTransaction['qr_code_url'] ?? null,
            'expires_at' => $lastTransaction['expires_at'] ?? null,
        ];
    }

    private function serializeGatewayEvent(BillingGatewayEvent $gatewayEvent): array
    {
        return [
            'id' => $gatewayEvent->id,
            'provider_key' => $gatewayEvent->provider_key,
            'event_key' => $gatewayEvent->event_key,
            'hook_id' => $gatewayEvent->hook_id,
            'event_type' => $gatewayEvent->event_type,
            'status' => $gatewayEvent->status,
            'billing_order_id' => $gatewayEvent->billing_order_id,
            'gateway_order_id' => $gatewayEvent->gateway_order_id,
            'gateway_subscription_id' => $gatewayEvent->gateway_subscription_id,
            'gateway_invoice_id' => $gatewayEvent->gateway_invoice_id,
            'gateway_cycle_id' => $gatewayEvent->gateway_cycle_id,
            'gateway_customer_id' => $gatewayEvent->gateway_customer_id,
            'gateway_charge_id' => $gatewayEvent->gateway_charge_id,
            'gateway_transaction_id' => $gatewayEvent->gateway_transaction_id,
            'processed_at' => $gatewayEvent->processed_at?->toISOString(),
        ];
    }

    private function isRecurringEventType(?string $eventType): bool
    {
        return Str::startsWith((string) $eventType, ['subscription.', 'invoice.', 'charge.']);
    }
}
