<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\BillingOrderStatus;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Services\BillingPaymentStatusNotificationService;
use Illuminate\Support\Carbon;

class MarkBillingOrderAsPaidAction
{
    public function __construct(
        private readonly BillingPaymentStatusNotificationService $paymentNotifications,
    ) {}

    public function execute(BillingOrder $billingOrder, array $attributes = []): array
    {
        $billingOrder->loadMissing([
            'items',
            'event:id,title',
            'payments',
            'invoices',
        ]);

        $paidAt = isset($attributes['paid_at'])
            ? Carbon::parse($attributes['paid_at'])
            : now();

        $gatewayProvider = $attributes['gateway_provider'] ?? $billingOrder->gateway_provider ?? 'manual';
        $gatewayOrderId = $attributes['gateway_order_id'] ?? $billingOrder->gateway_order_id;
        $gatewayPaymentId = $attributes['gateway_payment_id'] ?? $gatewayOrderId;
        $gatewayChargeId = $attributes['gateway_charge_id'] ?? $billingOrder->gateway_charge_id;
        $gatewayTransactionId = $attributes['gateway_transaction_id'] ?? $gatewayPaymentId;

        $billingOrder->forceFill([
            'status' => BillingOrderStatus::Paid->value,
            'gateway_provider' => $gatewayProvider,
            'gateway_order_id' => $gatewayOrderId,
            'gateway_charge_id' => $gatewayChargeId,
            'gateway_transaction_id' => $gatewayTransactionId,
            'gateway_status' => $attributes['gateway_status'] ?? BillingOrderStatus::Paid->value,
            'confirmed_at' => $billingOrder->confirmed_at ?? $paidAt,
            'paid_at' => $billingOrder->paid_at ?? $paidAt,
            'gateway_response_json' => $attributes['gateway_response'] ?? $billingOrder->gateway_response_json,
        ])->save();

        $payment = $billingOrder->payments()->first() ?? new Payment([
            'billing_order_id' => $billingOrder->id,
        ]);

        $payment->fill([
            'status' => PaymentStatus::Paid->value,
            'amount_cents' => $billingOrder->total_cents,
            'currency' => $billingOrder->currency,
            'payment_method' => $billingOrder->payment_method,
            'gateway_provider' => $gatewayProvider,
            'gateway_payment_id' => $gatewayPaymentId,
            'gateway_order_id' => $gatewayOrderId,
            'gateway_charge_id' => $gatewayChargeId,
            'gateway_transaction_id' => $gatewayTransactionId,
            'gateway_status' => $attributes['payment_status'] ?? $attributes['gateway_status'] ?? PaymentStatus::Paid->value,
            'paid_at' => $paidAt,
            'gateway_response_json' => $attributes['gateway_response'] ?? null,
            'last_transaction_json' => $attributes['last_transaction'] ?? null,
            'acquirer_return_code' => $attributes['acquirer_return_code'] ?? null,
            'acquirer_message' => $attributes['acquirer_message'] ?? null,
            'qr_code' => $attributes['qr_code'] ?? null,
            'qr_code_url' => $attributes['qr_code_url'] ?? null,
            'expires_at' => isset($attributes['expires_at']) ? Carbon::parse($attributes['expires_at']) : null,
            'raw_payload_json' => $attributes['payment_payload'] ?? [
                'source' => 'billing_order_mark_paid',
            ],
        ]);
        $payment->save();

        $invoice = $billingOrder->invoices()->first() ?? new Invoice([
            'organization_id' => $billingOrder->organization_id,
            'billing_order_id' => $billingOrder->id,
        ]);

        $invoice->fill([
            'status' => InvoiceStatus::Paid->value,
            'amount_cents' => $billingOrder->total_cents,
            'currency' => $billingOrder->currency,
            'issued_at' => $invoice->issued_at ?? $paidAt,
            'due_at' => $invoice->due_at ?? $paidAt,
            'paid_at' => $paidAt,
            'snapshot_json' => $attributes['invoice_snapshot'] ?? $this->buildInvoiceSnapshot($billingOrder, $payment),
        ]);
        $invoice->save();

        if (! $invoice->invoice_number) {
            $invoice->forceFill([
                'invoice_number' => sprintf('EVV-%s-%06d', $invoice->created_at?->format('Ymd') ?? now()->format('Ymd'), $invoice->id),
            ])->save();
        }

        $freshOrder = $billingOrder->fresh(['items', 'event', 'payments', 'invoices']);

        $this->paymentNotifications->queuePaymentPaid($freshOrder);

        return [
            'order' => $freshOrder,
            'payment' => $payment->fresh(),
            'invoice' => $invoice->fresh(),
        ];
    }

    private function buildInvoiceSnapshot(BillingOrder $billingOrder, Payment $payment): array
    {
        $primaryItem = $billingOrder->items->first();
        $itemSnapshot = (array) ($primaryItem?->snapshot_json ?? []);

        return array_filter([
            'order' => [
                'id' => $billingOrder->id,
                'uuid' => $billingOrder->uuid,
                'mode' => $billingOrder->mode?->value,
                'status' => $billingOrder->status?->value,
            ],
            'event' => $billingOrder->event ? [
                'id' => $billingOrder->event->id,
                'title' => $billingOrder->event->title,
            ] : null,
            'package' => $itemSnapshot['package'] ?? null,
            'plan' => $itemSnapshot['plan'] ?? null,
            'item' => $itemSnapshot,
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->status?->value,
                'gateway_provider' => $payment->gateway_provider,
                'gateway_payment_id' => $payment->gateway_payment_id,
                'paid_at' => $payment->paid_at?->toISOString(),
            ],
        ], fn (mixed $value): bool => $value !== null);
    }
}
