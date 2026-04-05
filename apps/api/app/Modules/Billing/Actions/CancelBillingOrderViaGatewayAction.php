<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\BillingOrderStatus;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Services\BillingGatewayManager;

class CancelBillingOrderViaGatewayAction
{
    public function __construct(
        private readonly BillingGatewayManager $gatewayManager,
        private readonly CancelBillingOrderAction $cancelBillingOrder,
        private readonly RefundBillingOrderAction $refundBillingOrder,
    ) {}

    public function execute(BillingOrder $billingOrder, array $data = []): array
    {
        $gateway = $this->gatewayManager->forProvider($billingOrder->gateway_provider);
        $gatewayResult = $gateway->cancelOrder($billingOrder, $data);
        $documentAttributes = [
            'gateway_provider' => $gatewayResult['provider_key'] ?? $billingOrder->gateway_provider ?? $gateway->providerKey(),
            'gateway_order_id' => $gatewayResult['gateway_order_id'] ?? $billingOrder->gateway_order_id,
            'gateway_payment_id' => $gatewayResult['gateway_charge_id']
                ?? $gatewayResult['gateway_order_id']
                ?? $billingOrder->gateway_charge_id
                ?? $billingOrder->gateway_order_id,
            'gateway_charge_id' => $gatewayResult['gateway_charge_id'] ?? $billingOrder->gateway_charge_id,
            'gateway_transaction_id' => $gatewayResult['gateway_transaction_id'] ?? $billingOrder->gateway_transaction_id,
            'gateway_status' => $gatewayResult['status'] ?? BillingOrderStatus::Canceled->value,
            'payment_status' => $gatewayResult['status'] ?? BillingOrderStatus::Canceled->value,
            'payment_payload' => [
                'source' => 'billing_order_cancel_via_gateway',
                'billing_order_uuid' => $billingOrder->uuid,
            ],
            'gateway_response' => $gatewayResult['gateway_response'] ?? $gatewayResult,
            'last_transaction' => $gatewayResult['last_transaction'] ?? null,
            'reason' => $data['reason'] ?? null,
        ];

        $shouldRefund = $billingOrder->status?->isPaid()
            || ($gatewayResult['status'] ?? null) === BillingOrderStatus::Refunded->value;

        if ($shouldRefund) {
            $result = $this->refundBillingOrder->execute($billingOrder, $documentAttributes + [
                'refunded_at' => now(),
                'purchase_status' => 'refunded',
            ]);

            return [
                ...$result,
                'message' => 'Estorno solicitado com sucesso.',
                'gateway' => $gatewayResult,
            ];
        }

        $order = $this->cancelBillingOrder->execute($billingOrder, $documentAttributes);

        return [
            'order' => $order->fresh(['payments', 'invoices', 'purchases']),
            'payment' => $order->payments()->latest()->first(),
            'invoice' => $order->invoices()->latest()->first(),
            'event' => $order->event?->fresh(['organization', 'modules']),
            'message' => 'Cancelamento solicitado com sucesso.',
            'gateway' => $gatewayResult,
        ];
    }
}
