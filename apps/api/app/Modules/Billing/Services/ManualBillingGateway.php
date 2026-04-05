<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Enums\BillingOrderStatus;
use App\Modules\Billing\Models\BillingOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ManualBillingGateway implements BillingGatewayInterface
{
    public function providerKey(): string
    {
        return 'manual';
    }

    public function createSubscriptionCheckout(BillingOrder $order, array $context = []): array
    {
        return [
            'provider_key' => $this->providerKey(),
            'gateway_order_id' => $context['gateway_order_id'] ?? "manual-subscription-{$order->uuid}",
            'status' => BillingOrderStatus::Paid->value,
            'checkout_url' => null,
            'confirm_url' => null,
            'expires_at' => null,
            'meta' => [
                'mode' => 'auto_capture',
            ],
        ];
    }

    public function createEventPackageCheckout(BillingOrder $order, array $context = []): array
    {
        return [
            'provider_key' => $this->providerKey(),
            'gateway_order_id' => $context['gateway_order_id'] ?? "manual-event-{$order->uuid}",
            'status' => BillingOrderStatus::PendingPayment->value,
            'checkout_url' => null,
            'confirm_url' => $context['confirm_url'] ?? url("/api/v1/public/event-checkouts/{$order->uuid}/confirm"),
            'expires_at' => null,
            'meta' => [
                'mode' => 'manual_confirmation',
            ],
        ];
    }

    public function parseWebhook(array $payload, array $headers = []): array
    {
        $validator = Validator::make($payload, [
            'event_key' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', 'in:payment.paid,checkout.canceled'],
            'billing_order_uuid' => ['nullable', 'uuid'],
            'gateway_order_id' => ['nullable', 'string', 'max:120'],
            'gateway_payment_id' => ['nullable', 'string', 'max:120'],
            'occurred_at' => ['nullable', 'date'],
            'data' => ['nullable', 'array'],
        ]);

        $validator->after(function ($validator) use ($payload) {
            if (blank($payload['billing_order_uuid'] ?? null) && blank($payload['gateway_order_id'] ?? null)) {
                $validator->errors()->add('billing_order_uuid', 'Informe billing_order_uuid ou gateway_order_id.');
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        return [
            'provider_key' => $this->providerKey(),
            'event_key' => $validated['event_key'],
            'event_type' => $validated['type'],
            'billing_order_uuid' => $validated['billing_order_uuid'] ?? null,
            'gateway_order_id' => $validated['gateway_order_id'] ?? null,
            'gateway_payment_id' => $validated['gateway_payment_id'] ?? null,
            'occurred_at' => isset($validated['occurred_at']) ? Carbon::parse($validated['occurred_at']) : now(),
            'payload' => $payload,
            'headers' => $headers,
        ];
    }

    public function cancelOrder(BillingOrder $order, array $context = []): array
    {
        return [
            'provider_key' => $this->providerKey(),
            'gateway_order_id' => $order->gateway_order_id ?: "manual-cancel-{$order->uuid}",
            'status' => BillingOrderStatus::Canceled->value,
            'meta' => [
                'reason' => $context['reason'] ?? 'manual_gateway_cancellation',
            ],
        ];
    }
}
