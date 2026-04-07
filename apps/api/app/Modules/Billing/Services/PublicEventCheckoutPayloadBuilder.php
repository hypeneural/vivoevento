<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Enums\BillingOrderNotificationType;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\BillingOrderNotification;
use App\Modules\Events\Support\EventCommercialStatusService;

class PublicEventCheckoutPayloadBuilder
{
    public function __construct(
        private readonly EventCommercialStatusService $commercialStatus,
    ) {}

    public function build(BillingOrder $order, array $extra = []): array
    {
        $order->loadMissing([
            'buyer.roles',
            'buyer.organizations',
            'organization',
            'event.organization',
            'event.modules',
            'items.eventPackage.prices',
            'items.eventPackage.features',
            'payments',
            'purchases.package',
            'notifications',
        ]);

        $event = $extra['event'] ?? $order->event?->fresh(['organization', 'modules']);
        $commercialStatus = $extra['commercial_status'] ?? ($event ? $this->commercialStatus->build($event) : null);
        $primaryItem = $order->items->first();
        $gateway = (array) ($order->metadata_json['gateway'] ?? []);
        $requestedPayment = (array) ($order->metadata_json['payment'] ?? []);
        $notifications = $order->notifications
            ->sortByDesc(fn ($notification) => sprintf('%015d-%015d', $notification->updated_at?->timestamp ?? 0, $notification->id));
        $latestPayment = $order->payments
            ->sortByDesc(fn ($payment) => sprintf('%015d-%015d', $payment->updated_at?->timestamp ?? 0, $payment->id))
            ->first();
        $purchase = $order->purchases
            ->sortByDesc(fn ($candidate) => sprintf(
                '%015d-%015d',
                $candidate->purchased_at?->timestamp ?? 0,
                $candidate->id,
            ))
            ->first();

        $paymentMethod = $order->payment_method ?? $requestedPayment['method'] ?? null;
        $paymentExpiresAt = $latestPayment?->expires_at?->toISOString()
            ?? $order->expires_at?->toISOString()
            ?? ($gateway['expires_at'] ?? null);
        $gatewayLastTransaction = (array) ($gateway['last_transaction'] ?? []);

        return [
            'message' => $extra['message'] ?? null,
            'token' => $extra['token'] ?? null,
            'user' => $extra['user'] ?? $order->buyer,
            'organization' => $extra['organization'] ?? $order->organization,
            'event' => $event,
            'commercial_status' => $commercialStatus,
            'checkout' => [
                'id' => $order->id,
                'uuid' => $order->uuid,
                'mode' => $order->mode?->value,
                'status' => $order->status?->value,
                'currency' => $order->currency,
                'total_cents' => $order->total_cents,
                'created_at' => $order->created_at?->toISOString(),
                'updated_at' => $order->updated_at?->toISOString(),
                'confirmed_at' => $order->confirmed_at?->toISOString(),
                'payment' => [
                    'provider' => $gateway['provider_key'] ?? $order->gateway_provider ?? 'manual',
                    'method' => $paymentMethod,
                    'gateway_order_id' => $gateway['gateway_order_id'] ?? $order->gateway_order_id,
                    'gateway_charge_id' => $latestPayment?->gateway_charge_id
                        ?? $order->gateway_charge_id
                        ?? ($gateway['gateway_charge_id'] ?? null),
                    'gateway_transaction_id' => $latestPayment?->gateway_transaction_id
                        ?? $order->gateway_transaction_id
                        ?? ($gateway['gateway_transaction_id'] ?? null),
                    'gateway_status' => $latestPayment?->gateway_status
                        ?? $order->gateway_status
                        ?? ($gateway['status'] ?? null),
                    'status' => $gateway['status'] ?? $order->status?->value,
                    'checkout_url' => $gateway['checkout_url'] ?? null,
                    'confirm_url' => $gateway['confirm_url'] ?? url("/api/v1/public/event-checkouts/{$order->uuid}/confirm"),
                    'expires_at' => $paymentExpiresAt,
                    'pix' => $paymentMethod === 'pix' ? [
                        'qr_code' => $latestPayment?->qr_code
                            ?? ($gateway['qr_code'] ?? null)
                            ?? ($gatewayLastTransaction['qr_code'] ?? null),
                        'qr_code_url' => $latestPayment?->qr_code_url
                            ?? ($gateway['qr_code_url'] ?? null)
                            ?? ($gatewayLastTransaction['qr_code_url'] ?? null),
                        'expires_at' => $paymentExpiresAt,
                    ] : null,
                    'credit_card' => $paymentMethod === 'credit_card' ? [
                        'installments' => data_get($requestedPayment, 'credit_card.installments'),
                        'acquirer_message' => $latestPayment?->acquirer_message
                            ?? ($gateway['acquirer_message'] ?? null)
                            ?? ($gatewayLastTransaction['acquirer_message'] ?? null),
                        'acquirer_return_code' => $latestPayment?->acquirer_return_code
                            ?? ($gateway['acquirer_return_code'] ?? null)
                            ?? ($gatewayLastTransaction['acquirer_return_code'] ?? null),
                        'last_status' => $latestPayment?->gateway_status
                            ?? $order->gateway_status
                            ?? ($gateway['status'] ?? null),
                    ] : null,
                    'whatsapp' => [
                        'pix_generated' => $this->serializeNotification(
                            $notifications->firstWhere('notification_type', BillingOrderNotificationType::PixGenerated),
                        ),
                        'payment_paid' => $this->serializeNotification(
                            $notifications->firstWhere('notification_type', BillingOrderNotificationType::PaymentPaid),
                        ),
                        'payment_failed' => $this->serializeNotification(
                            $notifications->firstWhere('notification_type', BillingOrderNotificationType::PaymentFailed),
                        ),
                        'payment_refunded' => $this->serializeNotification(
                            $notifications->firstWhere('notification_type', BillingOrderNotificationType::PaymentRefunded),
                        ),
                    ],
                    'meta' => $gateway['meta'] ?? [],
                ],
                'package' => $primaryItem?->snapshot_json['package'] ?? null,
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'item_type' => $item->item_type,
                    'reference_id' => $item->reference_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_amount_cents' => $item->unit_amount_cents,
                    'total_amount_cents' => $item->total_amount_cents,
                    'snapshot' => $item->snapshot_json,
                ])->values()->all(),
            ],
            'purchase' => $purchase ? [
                'id' => $purchase->id,
                'status' => $purchase->status,
                'package_id' => $purchase->package_id,
                'price_snapshot_cents' => $purchase->price_snapshot_cents,
                'currency' => $purchase->currency,
                'purchased_at' => $purchase->purchased_at?->toISOString(),
            ] : null,
            'onboarding' => $extra['onboarding'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeNotification(?BillingOrderNotification $notification): ?array
    {
        if (! $notification) {
            return null;
        }

        return [
            'type' => $notification->notification_type?->value,
            'status' => $notification->status,
            'recipient_phone' => $notification->recipient_phone,
            'dispatched_at' => $notification->dispatched_at?->toISOString(),
            'failed_at' => $notification->failed_at?->toISOString(),
            'whatsapp_message_id' => $notification->whatsapp_message_id,
            'pix_button_message_id' => data_get($notification->context_json, 'delivery.pix_button_message_id'),
            'pix_button_enabled' => data_get($notification->context_json, 'delivery.pix_button_enabled'),
            'pix_button_value_source' => data_get($notification->context_json, 'delivery.pix_button_value_source'),
        ];
    }
}
