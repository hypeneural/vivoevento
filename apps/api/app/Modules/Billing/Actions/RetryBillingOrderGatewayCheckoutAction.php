<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\BillingOrderMode;
use App\Modules\Billing\Enums\BillingOrderStatus;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\EventPurchase;
use App\Modules\Billing\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class RetryBillingOrderGatewayCheckoutAction
{
    private const LOCK_TTL_SECONDS = 30;

    public function __construct(
        private readonly CreateEventPackageGatewayCheckoutAction $createEventPackageGatewayCheckout,
    ) {}

    public function execute(BillingOrder $billingOrder): array
    {
        $order = BillingOrder::query()
            ->with(['payments', 'purchases'])
            ->findOrFail($billingOrder->id);

        if ($order->mode?->value !== BillingOrderMode::EventPackage->value) {
            throw ValidationException::withMessages([
                'billing_order' => ['A retentativa operacional so esta disponivel para checkout avulso de pacote por evento.'],
            ]);
        }

        $lock = Cache::lock($this->lockKey($order), self::LOCK_TTL_SECONDS);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'billing_order' => ['Ja existe uma tentativa de checkout em andamento para este pedido.'],
            ]);
        }

        try {
            $order = BillingOrder::query()
                ->with(['payments', 'purchases'])
                ->findOrFail($billingOrder->id);

            if ($this->isTerminalStatus($order)) {
                return $this->buildResult($order, 'skipped_terminal_order', false);
            }

            if (filled($order->gateway_order_id)) {
                return $this->buildResult($order, 'skipped_existing_gateway_snapshot', false);
            }

            $result = $this->createEventPackageGatewayCheckout->execute($order);
            $freshOrder = ($result['order'] ?? $order)->fresh(['payments', 'purchases']);

            return $this->buildResult(
                $freshOrder,
                'gateway_checkout_retried',
                true,
                $result['checkout'] ?? [],
                $result['payment'] ?? null,
                $result['purchase'] ?? null,
            );
        } finally {
            $lock->release();
        }
    }

    private function buildResult(
        BillingOrder $order,
        string $action,
        bool $externalCall,
        array $checkout = [],
        ?Payment $payment = null,
        ?EventPurchase $purchase = null,
    ): array {
        return [
            'order' => $order,
            'payment' => $payment ?? $order->payments->sortByDesc('id')->first(),
            'purchase' => $purchase ?? $order->purchases->sortByDesc('id')->first(),
            'retry' => [
                'provider' => $checkout['provider_key'] ?? $order->gateway_provider,
                'action' => $action,
                'external_call' => $externalCall,
                'idempotency_key' => $checkout['idempotency_key'] ?? $order->idempotency_key,
                'gateway_order_id' => $checkout['gateway_order_id'] ?? $order->gateway_order_id,
                'gateway_charge_id' => $checkout['gateway_charge_id'] ?? $order->gateway_charge_id,
                'gateway_transaction_id' => $checkout['gateway_transaction_id'] ?? $order->gateway_transaction_id,
                'status' => $checkout['status'] ?? $order->status?->value,
            ],
        ];
    }

    private function isTerminalStatus(BillingOrder $order): bool
    {
        return in_array($order->status, [
            BillingOrderStatus::Paid,
            BillingOrderStatus::Failed,
            BillingOrderStatus::Canceled,
            BillingOrderStatus::Refunded,
        ], true);
    }

    private function lockKey(BillingOrder $order): string
    {
        return "billing-order-gateway-checkout:{$order->id}";
    }
}
