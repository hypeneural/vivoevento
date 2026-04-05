<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\BillingOrderStatus;
use App\Modules\Billing\Models\BillingOrder;
use Illuminate\Support\Facades\DB;

class CancelBillingOrderAction
{
    public function execute(BillingOrder $billingOrder, array $data = []): BillingOrder
    {
        return DB::transaction(function () use ($billingOrder, $data) {
            /** @var BillingOrder $order */
            $order = BillingOrder::query()
                ->with(['buyer'])
                ->lockForUpdate()
                ->findOrFail($billingOrder->id);

            if ($order->status?->isPaid() || $order->status === BillingOrderStatus::Canceled) {
                return $order->fresh();
            }

            $gateway = array_merge(
                (array) ($order->metadata_json['gateway'] ?? []),
                array_filter([
                    'status' => BillingOrderStatus::Canceled->value,
                    'reason' => $data['reason'] ?? null,
                ], fn ($value) => $value !== null),
            );

            $order->forceFill([
                'status' => BillingOrderStatus::Canceled->value,
                'gateway_provider' => $data['gateway_provider'] ?? $order->gateway_provider,
                'gateway_order_id' => $data['gateway_order_id'] ?? $order->gateway_order_id,
                'gateway_charge_id' => $data['gateway_charge_id'] ?? $order->gateway_charge_id,
                'gateway_transaction_id' => $data['gateway_transaction_id'] ?? $order->gateway_transaction_id,
                'gateway_status' => $data['gateway_status'] ?? BillingOrderStatus::Canceled->value,
                'canceled_at' => $order->canceled_at ?? now(),
                'gateway_response_json' => $data['gateway_response'] ?? $order->gateway_response_json,
                'metadata_json' => array_merge($order->metadata_json ?? [], [
                    'gateway' => $gateway,
                ]),
            ])->save();

            activity()
                ->performedOn($order)
                ->causedBy($order->buyer)
                ->withProperties([
                    'gateway_provider' => $order->gateway_provider,
                    'gateway_order_id' => $order->gateway_order_id,
                    'reason' => $data['reason'] ?? null,
                ])
                ->log('Pedido cancelado pelo gateway');

            return $order->fresh();
        });
    }
}
