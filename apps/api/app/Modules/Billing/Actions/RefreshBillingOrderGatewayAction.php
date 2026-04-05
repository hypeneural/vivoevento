<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Services\Pagarme\PagarmeClient;
use App\Modules\Billing\Services\Pagarme\PagarmeStatusMapper;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefreshBillingOrderGatewayAction
{
    public function __construct(
        private readonly PagarmeClient $pagarmeClient,
        private readonly PagarmeStatusMapper $statusMapper,
        private readonly RegisterBillingGatewayPaymentAction $registerBillingGatewayPayment,
        private readonly FailBillingOrderAction $failBillingOrder,
        private readonly RefundBillingOrderAction $refundBillingOrder,
        private readonly CancelBillingOrderAction $cancelBillingOrder,
    ) {}

    public function execute(BillingOrder $billingOrder): array
    {
        $billingOrder = BillingOrder::query()
            ->with(['payments', 'purchases'])
            ->findOrFail($billingOrder->id);

        if (($billingOrder->gateway_provider ?? null) !== 'pagarme') {
            throw ValidationException::withMessages([
                'billing_order' => ['O refresh de troubleshooting so esta implementado para pedidos Pagar.me nesta fase.'],
            ]);
        }

        $gatewayOrder = $this->refreshOrderSnapshot($billingOrder);
        $gatewayChargeId = $billingOrder->gateway_charge_id
            ?? data_get($gatewayOrder, 'charges.0.id')
            ?? data_get($gatewayOrder, 'charge.id');
        $gatewayCharge = $this->refreshChargeSnapshot($gatewayChargeId);

        return $this->reconcileSnapshots($billingOrder, $gatewayOrder, $gatewayCharge);
    }

    private function refreshOrderSnapshot(BillingOrder $billingOrder): ?array
    {
        $gatewayOrderId = $billingOrder->gateway_order_id;

        if (! filled($gatewayOrderId)) {
            return null;
        }

        try {
            return $this->pagarmeClient->getOrder((string) $gatewayOrderId);
        } catch (RequestException $exception) {
            if ($exception->response?->status() === 404) {
                throw ValidationException::withMessages([
                    'billing_order' => ["A Pagar.me nao localizou o pedido {$gatewayOrderId} para refresh."],
                ]);
            }

            throw $exception;
        }
    }

    private function refreshChargeSnapshot(?string $gatewayChargeId): ?array
    {
        if (! filled($gatewayChargeId)) {
            return null;
        }

        try {
            return $this->pagarmeClient->getCharge((string) $gatewayChargeId);
        } catch (RequestException $exception) {
            if ($exception->response?->status() === 404) {
                throw ValidationException::withMessages([
                    'billing_order' => ["A Pagar.me nao localizou a cobranca {$gatewayChargeId} para refresh."],
                ]);
            }

            throw $exception;
        }
    }

    private function reconcileSnapshots(BillingOrder $billingOrder, ?array $gatewayOrder, ?array $gatewayCharge): array
    {
        return DB::transaction(function () use ($billingOrder, $gatewayOrder, $gatewayCharge) {
            /** @var BillingOrder $order */
            $order = BillingOrder::query()
                ->with(['payments', 'purchases'])
                ->lockForUpdate()
                ->findOrFail($billingOrder->id);

            $gatewayCharge = $gatewayCharge ?: (array) data_get($gatewayOrder, 'charges.0', []);
            $lastTransaction = (array) ($gatewayCharge['last_transaction'] ?? []);

            $gatewayOrderId = $order->gateway_order_id
                ?? data_get($gatewayOrder, 'id')
                ?? data_get($gatewayCharge, 'order.id');
            $gatewayChargeId = $order->gateway_charge_id
                ?? ($gatewayCharge['id'] ?? null);
            $gatewayTransactionId = $lastTransaction['id']
                ?? $lastTransaction['transaction_id']
                ?? $order->gateway_transaction_id;

            $gatewayOrderStatus = strtolower((string) ($gatewayOrder['status'] ?? ''));
            $gatewayChargeStatus = strtolower((string) ($gatewayCharge['status'] ?? ''));
            $gatewayTransactionStatus = strtolower((string) ($lastTransaction['status'] ?? ''));
            $effectiveGatewayStatus = $gatewayChargeStatus !== ''
                ? $gatewayChargeStatus
                : ($gatewayTransactionStatus !== '' ? $gatewayTransactionStatus : $gatewayOrderStatus);

            $gatewayResponse = array_filter([
                'source' => 'gateway_refresh',
                'provider' => 'pagarme',
                'refreshed_at' => now()->toISOString(),
                'order' => $gatewayOrder ?: null,
                'charge' => $gatewayCharge ?: null,
            ], fn (mixed $value): bool => $value !== null && $value !== []);

            $this->materializeOperationalSnapshot(
                $order,
                $gatewayOrder,
                $gatewayCharge,
                $lastTransaction,
                $effectiveGatewayStatus,
                $gatewayOrderId,
                $gatewayChargeId,
                $gatewayTransactionId,
                $gatewayResponse,
            );

            $result = [
                'action' => 'snapshot_refreshed',
                'transition_applied' => false,
            ];

            if (in_array($effectiveGatewayStatus, ['refunded', 'partial_canceled', 'partially_refunded', 'chargedback'], true)) {
                $purchaseStatus = $effectiveGatewayStatus === 'chargedback' ? 'chargedback' : 'refunded';

                $reconciled = $this->refundBillingOrder->execute($order, [
                    ...$this->buildGatewayDocumentAttributes(
                        $order,
                        $gatewayOrderId,
                        $gatewayChargeId,
                        $gatewayTransactionId,
                        $effectiveGatewayStatus,
                        $lastTransaction,
                        $gatewayResponse,
                    ),
                    'purchase_status' => $purchaseStatus,
                    'refunded_at' => now(),
                ]);

                $result = [
                    'action' => 'payment_refunded',
                    'transition_applied' => true,
                    'order' => $reconciled['order'],
                    'payment' => $reconciled['payment'] ?? null,
                    'purchase' => $reconciled['order']->purchases->sortByDesc('id')->first(),
                ];
            } elseif ($this->statusMapper->toBillingOrderStatus($effectiveGatewayStatus) === 'paid') {
                $reconciled = $this->registerBillingGatewayPayment->execute($order, [
                    ...$this->buildGatewayDocumentAttributes(
                        $order,
                        $gatewayOrderId,
                        $gatewayChargeId,
                        $gatewayTransactionId,
                        $effectiveGatewayStatus,
                        $lastTransaction,
                        $gatewayResponse,
                    ),
                    'paid_at' => now(),
                ]);

                $result = [
                    'action' => 'payment_registered',
                    'transition_applied' => true,
                    'order' => $reconciled['order'],
                    'payment' => $reconciled['payment'] ?? null,
                    'purchase' => $reconciled['purchase'] ?? null,
                ];
            } elseif (
                $this->statusMapper->toPaymentStatus($effectiveGatewayStatus) === 'failed'
                || $this->statusMapper->toBillingOrderStatus($effectiveGatewayStatus) === 'failed'
            ) {
                $reconciled = $this->failBillingOrder->execute($order, [
                    ...$this->buildGatewayDocumentAttributes(
                        $order,
                        $gatewayOrderId,
                        $gatewayChargeId,
                        $gatewayTransactionId,
                        $effectiveGatewayStatus,
                        $lastTransaction,
                        $gatewayResponse,
                    ),
                    'failed_at' => now(),
                ]);

                $result = [
                    'action' => 'payment_failed',
                    'transition_applied' => true,
                    'order' => $reconciled['order'],
                    'payment' => $reconciled['payment'] ?? null,
                    'purchase' => $reconciled['order']->purchases->sortByDesc('id')->first(),
                ];
            } elseif (
                in_array($effectiveGatewayStatus, ['canceled', 'cancelled'], true)
                || in_array($gatewayOrderStatus, ['canceled', 'cancelled'], true)
            ) {
                $reconciled = $this->cancelBillingOrder->execute($order, [
                    'gateway_provider' => 'pagarme',
                    'gateway_order_id' => $gatewayOrderId,
                    'gateway_charge_id' => $gatewayChargeId,
                    'gateway_transaction_id' => $gatewayTransactionId,
                    'gateway_status' => $effectiveGatewayStatus !== '' ? $effectiveGatewayStatus : 'canceled',
                    'gateway_response' => $gatewayResponse,
                    'reason' => 'gateway_refresh_canceled',
                ]);

                $result = [
                    'action' => 'order_canceled',
                    'transition_applied' => true,
                    'order' => $reconciled,
                    'payment' => $reconciled->payments()->latest('id')->first(),
                    'purchase' => $reconciled->purchases()->latest('id')->first(),
                ];
            }

            /** @var BillingOrder $refreshedOrder */
            $refreshedOrder = ($result['order'] ?? null) instanceof BillingOrder
                ? $result['order']->fresh(['payments', 'purchases'])
                : $order->fresh(['payments', 'purchases']);

            return [
                'order' => $refreshedOrder,
                'payment' => $result['payment'] ?? $refreshedOrder->payments->sortByDesc('id')->first(),
                'purchase' => $result['purchase'] ?? $refreshedOrder->purchases->sortByDesc('id')->first(),
                'sync' => [
                    'provider' => 'pagarme',
                    'action' => $result['action'],
                    'transition_applied' => $result['transition_applied'],
                    'fetched_at' => now()->toISOString(),
                ],
                'gateway' => [
                    'order' => $gatewayOrder,
                    'charge' => $gatewayCharge ?: null,
                ],
            ];
        });
    }

    private function materializeOperationalSnapshot(
        BillingOrder $order,
        ?array $gatewayOrder,
        array $gatewayCharge,
        array $lastTransaction,
        string $effectiveGatewayStatus,
        ?string $gatewayOrderId,
        ?string $gatewayChargeId,
        ?string $gatewayTransactionId,
        array $gatewayResponse,
    ): void {
        $gatewayMetadata = array_merge(
            (array) data_get($order->metadata_json, 'gateway', []),
            array_filter([
                'provider_key' => 'pagarme',
                'gateway_order_id' => $gatewayOrderId,
                'gateway_charge_id' => $gatewayChargeId,
                'gateway_transaction_id' => $gatewayTransactionId,
                'status' => $effectiveGatewayStatus !== '' ? $effectiveGatewayStatus : $order->gateway_status,
                'payment_method' => $gatewayCharge['payment_method'] ?? data_get($gatewayOrder, 'charges.0.payment_method') ?? $order->payment_method,
                'expires_at' => $lastTransaction['expires_at'] ?? null,
                'qr_code' => $lastTransaction['qr_code'] ?? null,
                'qr_code_url' => $lastTransaction['qr_code_url'] ?? null,
                'acquirer_message' => $lastTransaction['acquirer_message'] ?? null,
                'acquirer_return_code' => $lastTransaction['acquirer_return_code'] ?? null,
                'last_transaction' => $lastTransaction ?: null,
                'meta' => array_filter([
                    'order_status' => data_get($gatewayOrder, 'status'),
                    'charge_status' => $gatewayCharge['status'] ?? null,
                    'transaction_status' => $lastTransaction['status'] ?? null,
                ], fn (mixed $value): bool => $value !== null && $value !== ''),
            ], fn (mixed $value): bool => $value !== null && $value !== [] && $value !== ''),
        );

        $order->forceFill([
            'gateway_provider' => 'pagarme',
            'gateway_order_id' => $gatewayOrderId ?? $order->gateway_order_id,
            'gateway_charge_id' => $gatewayChargeId ?? $order->gateway_charge_id,
            'gateway_transaction_id' => $gatewayTransactionId ?? $order->gateway_transaction_id,
            'gateway_status' => $effectiveGatewayStatus !== '' ? $effectiveGatewayStatus : $order->gateway_status,
            'expires_at' => isset($lastTransaction['expires_at']) ? Carbon::parse($lastTransaction['expires_at']) : $order->expires_at,
            'gateway_response_json' => $gatewayResponse,
            'metadata_json' => array_merge($order->metadata_json ?? [], [
                'gateway' => $gatewayMetadata,
            ]),
        ])->save();

        $payment = $order->payments->sortByDesc('id')->first();

        if ($payment instanceof Payment) {
            $payment->forceFill([
                'gateway_provider' => 'pagarme',
                'gateway_order_id' => $gatewayOrderId ?? $payment->gateway_order_id,
                'gateway_charge_id' => $gatewayChargeId ?? $payment->gateway_charge_id,
                'gateway_transaction_id' => $gatewayTransactionId ?? $payment->gateway_transaction_id,
                'gateway_status' => $effectiveGatewayStatus !== '' ? $effectiveGatewayStatus : $payment->gateway_status,
                'expires_at' => isset($lastTransaction['expires_at']) ? Carbon::parse($lastTransaction['expires_at']) : $payment->expires_at,
                'last_transaction_json' => $lastTransaction ?: $payment->last_transaction_json,
                'gateway_response_json' => $gatewayResponse,
                'acquirer_return_code' => $lastTransaction['acquirer_return_code'] ?? $payment->acquirer_return_code,
                'acquirer_message' => $lastTransaction['acquirer_message'] ?? $payment->acquirer_message,
                'qr_code' => $lastTransaction['qr_code'] ?? $payment->qr_code,
                'qr_code_url' => $lastTransaction['qr_code_url'] ?? $payment->qr_code_url,
            ])->save();
        }
    }

    private function buildGatewayDocumentAttributes(
        BillingOrder $order,
        ?string $gatewayOrderId,
        ?string $gatewayChargeId,
        ?string $gatewayTransactionId,
        string $effectiveGatewayStatus,
        array $lastTransaction,
        array $gatewayResponse,
    ): array {
        return [
            'gateway_provider' => 'pagarme',
            'gateway_order_id' => $gatewayOrderId ?? $order->gateway_order_id,
            'gateway_payment_id' => $gatewayChargeId ?? $gatewayOrderId ?? $order->gateway_charge_id ?? $order->gateway_order_id,
            'gateway_charge_id' => $gatewayChargeId ?? $order->gateway_charge_id,
            'gateway_transaction_id' => $gatewayTransactionId ?? $order->gateway_transaction_id,
            'gateway_status' => $effectiveGatewayStatus !== '' ? $effectiveGatewayStatus : $order->gateway_status,
            'payment_status' => $effectiveGatewayStatus !== ''
                ? $effectiveGatewayStatus
                : $this->statusMapper->toPaymentStatus($effectiveGatewayStatus),
            'payment_payload' => $gatewayResponse,
            'gateway_response' => $gatewayResponse,
            'last_transaction' => $lastTransaction ?: null,
            'acquirer_return_code' => $lastTransaction['acquirer_return_code'] ?? null,
            'acquirer_message' => $lastTransaction['acquirer_message'] ?? null,
            'qr_code' => $lastTransaction['qr_code'] ?? null,
            'qr_code_url' => $lastTransaction['qr_code_url'] ?? null,
            'expires_at' => $lastTransaction['expires_at'] ?? null,
        ];
    }
}
