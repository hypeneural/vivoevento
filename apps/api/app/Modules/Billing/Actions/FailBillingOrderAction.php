<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\BillingOrderStatus;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Services\BillingPaymentStatusNotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FailBillingOrderAction
{
    public function __construct(
        private readonly BillingPaymentStatusNotificationService $paymentNotifications,
    ) {}

    public function execute(BillingOrder $billingOrder, array $data = []): array
    {
        $failedAt = isset($data['failed_at']) ? Carbon::parse($data['failed_at']) : now();

        $result = DB::transaction(function () use ($billingOrder, $data, $failedAt) {
            /** @var BillingOrder $order */
            $order = BillingOrder::query()
                ->with(['payments'])
                ->lockForUpdate()
                ->findOrFail($billingOrder->id);

            if ($order->status === BillingOrderStatus::Failed) {
                return [
                    'order' => $order->fresh(['payments']),
                    'payment' => $order->payments->sortByDesc('id')->first(),
                ];
            }

            $order->forceFill([
                'status' => BillingOrderStatus::Failed->value,
                'gateway_provider' => $data['gateway_provider'] ?? $order->gateway_provider,
                'gateway_order_id' => $data['gateway_order_id'] ?? $order->gateway_order_id,
                'gateway_charge_id' => $data['gateway_charge_id'] ?? $order->gateway_charge_id,
                'gateway_transaction_id' => $data['gateway_transaction_id'] ?? $order->gateway_transaction_id,
                'gateway_status' => $data['gateway_status'] ?? 'failed',
                'failed_at' => $order->failed_at ?? $failedAt,
                'gateway_response_json' => $data['gateway_response'] ?? $order->gateway_response_json,
            ])->save();

            $payment = $order->payments()->first() ?? new Payment([
                'billing_order_id' => $order->id,
            ]);

            $payment->fill([
                'status' => PaymentStatus::Failed->value,
                'amount_cents' => $order->total_cents,
                'currency' => $order->currency,
                'payment_method' => $order->payment_method,
                'gateway_provider' => $data['gateway_provider'] ?? $order->gateway_provider,
                'gateway_payment_id' => $data['gateway_payment_id'] ?? $data['gateway_charge_id'] ?? $order->gateway_charge_id ?? $order->gateway_order_id,
                'gateway_order_id' => $data['gateway_order_id'] ?? $order->gateway_order_id,
                'gateway_charge_id' => $data['gateway_charge_id'] ?? $order->gateway_charge_id,
                'gateway_transaction_id' => $data['gateway_transaction_id'] ?? $order->gateway_transaction_id,
                'gateway_status' => $data['payment_status'] ?? $data['gateway_status'] ?? 'failed',
                'failed_at' => $payment->failed_at ?? $failedAt,
                'gateway_response_json' => $data['gateway_response'] ?? null,
                'last_transaction_json' => $data['last_transaction'] ?? null,
                'acquirer_return_code' => $data['acquirer_return_code'] ?? null,
                'acquirer_message' => $data['acquirer_message'] ?? null,
                'raw_payload_json' => $data['payment_payload'] ?? [
                    'source' => 'billing_order_mark_failed',
                ],
            ]);
            $payment->save();

            return [
                'order' => $order->fresh(['payments']),
                'payment' => $payment->fresh(),
            ];
        });

        $this->paymentNotifications->queuePaymentFailed($result['order']);

        return $result;
    }
}
