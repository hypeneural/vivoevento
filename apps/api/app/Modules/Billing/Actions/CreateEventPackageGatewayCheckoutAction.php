<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\BillingOrderStatus;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Services\BillingGatewayManager;
use App\Modules\Billing\Services\BillingPaymentStatusNotificationService;
use Illuminate\Support\Carbon;

class CreateEventPackageGatewayCheckoutAction
{
    public function __construct(
        private readonly BillingGatewayManager $gatewayManager,
        private readonly RegisterBillingGatewayPaymentAction $registerBillingGatewayPayment,
        private readonly FailBillingOrderAction $failBillingOrder,
        private readonly BillingPaymentStatusNotificationService $paymentNotifications,
    ) {}

    public function execute(BillingOrder $billingOrder, array $context = []): array
    {
        $gateway = $this->gatewayManager->forMode($billingOrder->mode ?? 'event_package');
        $checkout = $gateway->createEventPackageCheckout($billingOrder, $context);
        $orderStatus = $checkout['status'] ?? BillingOrderStatus::PendingPayment->value;
        $shouldFinalizeSynchronously = in_array($orderStatus, [
            BillingOrderStatus::Paid->value,
            BillingOrderStatus::Failed->value,
        ], true);

        $billingOrder->forceFill([
            'gateway_provider' => $checkout['provider_key'] ?? $gateway->providerKey(),
            'gateway_order_id' => $checkout['gateway_order_id'] ?? $billingOrder->gateway_order_id,
            'idempotency_key' => $checkout['idempotency_key'] ?? $billingOrder->idempotency_key,
            'gateway_charge_id' => $checkout['gateway_charge_id'] ?? $billingOrder->gateway_charge_id,
            'gateway_transaction_id' => $checkout['gateway_transaction_id'] ?? $billingOrder->gateway_transaction_id,
            'gateway_status' => $checkout['status'] ?? $billingOrder->gateway_status ?? BillingOrderStatus::PendingPayment->value,
            'status' => $shouldFinalizeSynchronously
                ? ($billingOrder->status?->value ?? BillingOrderStatus::PendingPayment->value)
                : $orderStatus,
            'expires_at' => isset($checkout['expires_at']) ? Carbon::parse($checkout['expires_at']) : $billingOrder->expires_at,
            'gateway_response_json' => $checkout,
            'metadata_json' => array_merge($billingOrder->metadata_json ?? [], [
                'gateway' => [
                    'provider_key' => $checkout['provider_key'] ?? $gateway->providerKey(),
                    'gateway_order_id' => $checkout['gateway_order_id'] ?? null,
                    'gateway_charge_id' => $checkout['gateway_charge_id'] ?? null,
                    'gateway_transaction_id' => $checkout['gateway_transaction_id'] ?? null,
                    'status' => $checkout['status'] ?? BillingOrderStatus::PendingPayment->value,
                    'checkout_url' => $checkout['checkout_url'] ?? null,
                    'confirm_url' => $checkout['confirm_url'] ?? null,
                    'expires_at' => $checkout['expires_at'] ?? null,
                    'payment_method' => $checkout['payment_method'] ?? null,
                    'qr_code' => $checkout['qr_code'] ?? null,
                    'qr_code_url' => $checkout['qr_code_url'] ?? null,
                    'acquirer_message' => $checkout['acquirer_message'] ?? null,
                    'acquirer_return_code' => $checkout['acquirer_return_code'] ?? null,
                    'last_transaction' => $checkout['last_transaction'] ?? null,
                    'meta' => $checkout['meta'] ?? [],
                ],
            ]),
        ])->save();

        $paymentMethod = $checkout['payment_method'] ?? $billingOrder->payment_method;
        $hasPixPayload = filled($checkout['qr_code'] ?? null) || filled($checkout['qr_code_url'] ?? null);

        if (
            $paymentMethod === 'pix'
            && $hasPixPayload
            && ! in_array($orderStatus, [BillingOrderStatus::Paid->value, BillingOrderStatus::Failed->value], true)
        ) {
            $this->paymentNotifications->queuePixGenerated($billingOrder);
        }

        if ($orderStatus === BillingOrderStatus::Paid->value) {
            return [
                ...$this->registerBillingGatewayPayment->execute(
                    $billingOrder,
                    $this->buildGatewayDocumentAttributes($billingOrder, $checkout),
                ),
                'checkout' => $checkout,
            ];
        }

        if ($orderStatus === BillingOrderStatus::Failed->value) {
            return [
                ...$this->failBillingOrder->execute(
                    $billingOrder,
                    $this->buildGatewayDocumentAttributes($billingOrder, $checkout) + [
                        'failed_at' => now(),
                    ],
                ),
                'checkout' => $checkout,
            ];
        }

        return [
            'order' => $billingOrder->fresh(),
            'checkout' => $checkout,
        ];
    }

    private function buildGatewayDocumentAttributes(BillingOrder $billingOrder, array $checkout): array
    {
        return [
            'gateway_provider' => $checkout['provider_key'] ?? $billingOrder->gateway_provider ?? 'manual',
            'gateway_order_id' => $checkout['gateway_order_id'] ?? $billingOrder->gateway_order_id,
            'gateway_payment_id' => $checkout['gateway_charge_id']
                ?? $checkout['gateway_order_id']
                ?? $billingOrder->gateway_charge_id
                ?? $billingOrder->gateway_order_id,
            'gateway_charge_id' => $checkout['gateway_charge_id'] ?? $billingOrder->gateway_charge_id,
            'gateway_transaction_id' => $checkout['gateway_transaction_id'] ?? $billingOrder->gateway_transaction_id,
            'gateway_status' => $checkout['status'] ?? $billingOrder->gateway_status ?? BillingOrderStatus::PendingPayment->value,
            'payment_status' => $checkout['status'] ?? $billingOrder->gateway_status ?? BillingOrderStatus::PendingPayment->value,
            'payment_payload' => [
                'source' => 'gateway_checkout_create',
                'billing_order_uuid' => $billingOrder->uuid,
            ],
            'gateway_response' => $checkout,
            'last_transaction' => $checkout['last_transaction'] ?? null,
            'acquirer_return_code' => $checkout['acquirer_return_code'] ?? null,
            'acquirer_message' => $checkout['acquirer_message'] ?? null,
            'qr_code' => $checkout['qr_code'] ?? null,
            'qr_code_url' => $checkout['qr_code_url'] ?? null,
            'expires_at' => $checkout['expires_at'] ?? null,
        ];
    }
}
