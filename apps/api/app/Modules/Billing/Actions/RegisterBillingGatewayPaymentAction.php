<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\BillingOrderMode;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterBillingGatewayPaymentAction
{
    public function __construct(
        private readonly MarkBillingOrderAsPaidAction $markBillingOrderAsPaid,
        private readonly ActivatePaidEventPackageOrderAction $activatePaidEventPackageOrder,
    ) {}

    public function execute(BillingOrder $billingOrder, array $data = []): array
    {
        return match ($billingOrder->mode?->value) {
            BillingOrderMode::Subscription->value => $this->registerSubscriptionPayment($billingOrder, $data),
            BillingOrderMode::EventPackage->value => $this->activatePaidEventPackageOrder->execute($billingOrder, $data),
            default => throw ValidationException::withMessages([
                'billing_order' => ['O tipo deste pedido nao suporta confirmacao de pagamento.'],
            ]),
        };
    }

    private function registerSubscriptionPayment(BillingOrder $billingOrder, array $data): array
    {
        $paidAt = isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now();

        return DB::transaction(function () use ($billingOrder, $data, $paidAt) {
            /** @var BillingOrder $order */
            $order = BillingOrder::query()
                ->with(['items', 'payments', 'invoices', 'organization', 'buyer'])
                ->lockForUpdate()
                ->findOrFail($billingOrder->id);

            $planItem = $order->items->firstWhere('item_type', 'subscription_plan') ?? $order->items->first();
            $planId = (int) ($planItem?->reference_id ?? ($order->metadata_json['plan_id'] ?? 0));
            $billingCycle = (string) ($order->metadata_json['billing_cycle'] ?? 'monthly');

            if ($planId <= 0) {
                throw ValidationException::withMessages([
                    'billing_order' => ['Nao foi possivel identificar o plano associado ao pedido.'],
                ]);
            }

            $billingDocuments = $order->status?->isPaid()
                ? [
                    'order' => $order->fresh(['items', 'event', 'payments', 'invoices']),
                    'payment' => $order->payments->sortByDesc('id')->first(),
                    'invoice' => $order->invoices->sortByDesc('id')->first(),
                ]
                : $this->markBillingOrderAsPaid->execute($order, [
                    'paid_at' => $paidAt,
                    'gateway_provider' => $data['gateway_provider'] ?? $order->gateway_provider ?? 'manual',
                    'gateway_order_id' => $data['gateway_order_id'] ?? $order->gateway_order_id,
                    'gateway_payment_id' => $data['gateway_payment_id'] ?? $data['gateway_order_id'] ?? $order->gateway_order_id,
                    'gateway_charge_id' => $data['gateway_charge_id'] ?? $order->gateway_charge_id,
                    'gateway_transaction_id' => $data['gateway_transaction_id'] ?? $order->gateway_transaction_id,
                    'gateway_status' => $data['gateway_status'] ?? 'paid',
                    'payment_status' => $data['payment_status'] ?? 'paid',
                    'payment_payload' => $data['payment_payload'] ?? [
                        'source' => 'billing_gateway_payment',
                        'billing_order_uuid' => $order->uuid,
                    ],
                    'gateway_response' => $data['gateway_response'] ?? null,
                    'last_transaction' => $data['last_transaction'] ?? null,
                    'acquirer_return_code' => $data['acquirer_return_code'] ?? null,
                    'acquirer_message' => $data['acquirer_message'] ?? null,
                    'qr_code' => $data['qr_code'] ?? null,
                    'qr_code_url' => $data['qr_code_url'] ?? null,
                    'expires_at' => $data['expires_at'] ?? null,
                    'invoice_snapshot' => $data['invoice_snapshot'] ?? null,
                ]);

            $subscription = Subscription::updateOrCreate(
                ['organization_id' => $order->organization_id],
                [
                    'plan_id' => $planId,
                    'status' => 'active',
                    'billing_cycle' => $billingCycle,
                    'starts_at' => $paidAt,
                    'renews_at' => $billingCycle === 'yearly' ? $paidAt->copy()->addYear() : $paidAt->copy()->addMonth(),
                    'ends_at' => null,
                    'canceled_at' => null,
                    'gateway_provider' => $data['gateway_provider'] ?? $order->gateway_provider ?? 'manual',
                    'gateway_subscription_id' => $data['gateway_subscription_id'] ?? $order->gateway_order_id,
                ]
            );

            activity()
                ->performedOn($subscription)
                ->causedBy($order->buyer)
                ->withProperties([
                    'billing_order_id' => $order->id,
                    'plan_id' => $planId,
                    'billing_cycle' => $billingCycle,
                    'provider' => $order->gateway_provider,
                ])
                ->log('Pagamento de assinatura registrado pelo gateway');

            return [
                'order' => $billingDocuments['order'],
                'payment' => $billingDocuments['payment'],
                'invoice' => $billingDocuments['invoice'],
                'subscription' => $subscription->fresh(['plan']),
            ];
        });
    }
}
