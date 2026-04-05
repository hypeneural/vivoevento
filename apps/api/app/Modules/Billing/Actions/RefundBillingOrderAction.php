<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\BillingOrderStatus;
use App\Modules\Billing\Enums\EventAccessGrantSourceType;
use App\Modules\Billing\Enums\EventAccessGrantStatus;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\EventAccessGrant;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Services\BillingPaymentStatusNotificationService;
use App\Modules\Events\Support\EventCommercialStatusService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RefundBillingOrderAction
{
    public function __construct(
        private readonly EventCommercialStatusService $commercialStatus,
        private readonly BillingPaymentStatusNotificationService $paymentNotifications,
    ) {}

    public function execute(BillingOrder $billingOrder, array $data = []): array
    {
        $refundedAt = isset($data['refunded_at']) ? Carbon::parse($data['refunded_at']) : now();
        $purchaseStatus = $data['purchase_status'] ?? 'refunded';

        $result = DB::transaction(function () use ($billingOrder, $data, $refundedAt, $purchaseStatus) {
            /** @var BillingOrder $order */
            $order = BillingOrder::query()
                ->with(['payments', 'invoices', 'purchases', 'event.organization', 'event.modules'])
                ->lockForUpdate()
                ->findOrFail($billingOrder->id);

            $order->forceFill([
                'status' => BillingOrderStatus::Refunded->value,
                'gateway_provider' => $data['gateway_provider'] ?? $order->gateway_provider,
                'gateway_order_id' => $data['gateway_order_id'] ?? $order->gateway_order_id,
                'gateway_charge_id' => $data['gateway_charge_id'] ?? $order->gateway_charge_id,
                'gateway_transaction_id' => $data['gateway_transaction_id'] ?? $order->gateway_transaction_id,
                'gateway_status' => $data['gateway_status'] ?? 'refunded',
                'refunded_at' => $order->refunded_at ?? $refundedAt,
                'gateway_response_json' => $data['gateway_response'] ?? $order->gateway_response_json,
            ])->save();

            $payment = $order->payments()->first() ?? new Payment([
                'billing_order_id' => $order->id,
            ]);

            $payment->fill([
                'status' => PaymentStatus::Refunded->value,
                'amount_cents' => $order->total_cents,
                'currency' => $order->currency,
                'payment_method' => $order->payment_method,
                'gateway_provider' => $data['gateway_provider'] ?? $order->gateway_provider,
                'gateway_payment_id' => $data['gateway_payment_id'] ?? $data['gateway_charge_id'] ?? $order->gateway_charge_id ?? $order->gateway_order_id,
                'gateway_order_id' => $data['gateway_order_id'] ?? $order->gateway_order_id,
                'gateway_charge_id' => $data['gateway_charge_id'] ?? $order->gateway_charge_id,
                'gateway_transaction_id' => $data['gateway_transaction_id'] ?? $order->gateway_transaction_id,
                'gateway_status' => $data['payment_status'] ?? $data['gateway_status'] ?? 'refunded',
                'refunded_at' => $payment->refunded_at ?? $refundedAt,
                'gateway_response_json' => $data['gateway_response'] ?? null,
                'last_transaction_json' => $data['last_transaction'] ?? null,
                'raw_payload_json' => $data['payment_payload'] ?? [
                    'source' => 'billing_order_mark_refunded',
                ],
            ]);
            $payment->save();

            $invoice = $order->invoices()->first() ?? new Invoice([
                'organization_id' => $order->organization_id,
                'billing_order_id' => $order->id,
            ]);

            $invoice->fill([
                'status' => InvoiceStatus::Refunded->value,
                'amount_cents' => $order->total_cents,
                'currency' => $order->currency,
                'issued_at' => $invoice->issued_at ?? $refundedAt,
                'due_at' => $invoice->due_at ?? $refundedAt,
                'paid_at' => $invoice->paid_at ?? $refundedAt,
                'snapshot_json' => $invoice->snapshot_json ?? [],
            ]);
            $invoice->save();

            foreach ($order->purchases as $purchase) {
                $purchase->forceFill([
                    'status' => $purchaseStatus,
                ])->save();

                EventAccessGrant::query()
                    ->where('event_id', $purchase->event_id)
                    ->where('source_type', EventAccessGrantSourceType::EventPurchase->value)
                    ->where('source_id', $purchase->id)
                    ->update([
                        'status' => EventAccessGrantStatus::Revoked->value,
                        'ends_at' => $refundedAt,
                    ]);
            }

            $event = $order->event ? $this->commercialStatus->sync($order->event->fresh(['organization', 'modules'])) : null;

            return [
                'order' => $order->fresh(['payments', 'invoices', 'purchases']),
                'payment' => $payment->fresh(),
                'invoice' => $invoice->fresh(),
                'event' => $event,
            ];
        });

        if (($data['purchase_status'] ?? null) !== 'chargedback') {
            $this->paymentNotifications->queuePaymentRefunded($result['order']);
        }

        return $result;
    }
}
