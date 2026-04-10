<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Enums\BillingOrderNotificationType;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\BillingOrderNotification;
use App\Modules\Events\Support\EventCommercialStatusService;
use Illuminate\Support\Carbon;

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
        $paymentProvider = $gateway['provider_key'] ?? $order->gateway_provider ?? 'manual';
        $paymentGatewayStatus = $latestPayment?->gateway_status
            ?? $order->gateway_status
            ?? ($gateway['status'] ?? null);
        $paymentStatus = $latestPayment?->status?->value
            ?? ($gateway['status'] ?? null)
            ?? $order->status?->value;
        $creditCardLastStatus = $latestPayment?->gateway_status
            ?? $order->gateway_status
            ?? ($gateway['status'] ?? null);
        $acquirerMessage = $latestPayment?->acquirer_message
            ?? ($gateway['acquirer_message'] ?? null)
            ?? ($gatewayLastTransaction['acquirer_message'] ?? null);
        $summary = $this->buildCheckoutSummary(
            orderStatus: $order->status?->value,
            paymentMethod: is_string($paymentMethod) ? $paymentMethod : null,
            paymentStatus: is_string($paymentStatus) ? $paymentStatus : null,
            gatewayStatus: is_string($paymentGatewayStatus) ? $paymentGatewayStatus : null,
            creditCardLastStatus: is_string($creditCardLastStatus) ? $creditCardLastStatus : null,
            paymentExpiresAt: is_string($paymentExpiresAt) ? $paymentExpiresAt : null,
            acquirerMessage: is_string($acquirerMessage) ? $acquirerMessage : null,
        );
        $paymentMeta = array_filter(array_merge(
            (array) ($gateway['meta'] ?? []),
            [
                'charge_status' => $paymentGatewayStatus,
                'gateway_status' => $paymentGatewayStatus,
                'payment_method' => $paymentMethod,
            ],
        ), fn (mixed $value): bool => $value !== null && $value !== '');

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
                'summary' => $summary,
                'payment' => [
                    'provider' => $paymentProvider,
                    'method' => $paymentMethod,
                    'gateway_order_id' => $gateway['gateway_order_id'] ?? $order->gateway_order_id,
                    'gateway_charge_id' => $latestPayment?->gateway_charge_id
                        ?? $order->gateway_charge_id
                        ?? ($gateway['gateway_charge_id'] ?? null),
                    'gateway_transaction_id' => $latestPayment?->gateway_transaction_id
                        ?? $order->gateway_transaction_id
                        ?? ($gateway['gateway_transaction_id'] ?? null),
                    'gateway_status' => $paymentGatewayStatus,
                    'status' => $paymentStatus,
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
                        'acquirer_message' => $acquirerMessage,
                        'acquirer_return_code' => $latestPayment?->acquirer_return_code
                            ?? ($gateway['acquirer_return_code'] ?? null)
                            ?? ($gatewayLastTransaction['acquirer_return_code'] ?? null),
                        'last_status' => $creditCardLastStatus,
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
                    'meta' => $paymentMeta,
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
     * @return array<string, mixed>
     */
    private function buildCheckoutSummary(
        ?string $orderStatus,
        ?string $paymentMethod,
        ?string $paymentStatus,
        ?string $gatewayStatus,
        ?string $creditCardLastStatus,
        ?string $paymentExpiresAt,
        ?string $acquirerMessage,
    ): array {
        $statuses = array_values(array_filter([
            $orderStatus,
            $paymentStatus,
            $gatewayStatus,
            $creditCardLastStatus,
        ], fn (mixed $value): bool => is_string($value) && $value !== ''));

        $expiresInSeconds = $this->calculateExpiresInSeconds($paymentExpiresAt);
        $pixExpired = $paymentMethod === 'pix'
            && $expiresInSeconds !== null
            && $expiresInSeconds <= 0
            && ! in_array('paid', $statuses, true)
            && ! in_array('refunded', $statuses, true)
            && ! in_array('chargedback', $statuses, true)
            && ! in_array('failed', $statuses, true)
            && ! in_array('canceled', $statuses, true);

        if (in_array('paid', $statuses, true)) {
            return [
                'state' => 'paid',
                'tone' => 'success',
                'payment_status_title' => 'Pagamento confirmado',
                'order_status_label' => 'Pedido confirmado',
                'payment_status_label' => 'Confirmado',
                'payment_status_description' => 'Seu pacote ja foi confirmado e o evento pode seguir para a ativacao.',
                'next_action' => 'open_event',
                'expires_in_seconds' => $expiresInSeconds,
                'is_waiting_payment' => false,
                'can_retry' => false,
            ];
        }

        if (in_array('refunded', $statuses, true) || in_array('chargedback', $statuses, true)) {
            return [
                'state' => 'refunded',
                'tone' => 'warning',
                'payment_status_title' => 'Cobranca revertida',
                'order_status_label' => 'Pedido revertido',
                'payment_status_label' => 'Revertido',
                'payment_status_description' => 'O pedido foi atualizado como estornado ou revertido. Se precisar, faca uma nova tentativa de compra.',
                'next_action' => 'contact_support',
                'expires_in_seconds' => null,
                'is_waiting_payment' => false,
                'can_retry' => false,
            ];
        }

        if ($pixExpired) {
            return [
                'state' => 'failed',
                'tone' => 'error',
                'payment_status_title' => 'Pix expirado',
                'order_status_label' => 'Pedido criado',
                'payment_status_label' => 'Pix expirado',
                'payment_status_description' => 'O prazo deste Pix terminou. Gere uma nova tentativa de pagamento para continuar.',
                'next_action' => 'retry_payment',
                'expires_in_seconds' => 0,
                'is_waiting_payment' => false,
                'can_retry' => true,
            ];
        }

        if (in_array('failed', $statuses, true) || in_array('canceled', $statuses, true)) {
            return [
                'state' => 'failed',
                'tone' => 'error',
                'payment_status_title' => 'Pagamento nao confirmado',
                'order_status_label' => 'Pedido nao confirmado',
                'payment_status_label' => 'Nao confirmado',
                'payment_status_description' => $acquirerMessage
                    ?: 'Nao foi possivel confirmar esta tentativa de pagamento. Voce pode revisar os dados e tentar novamente.',
                'next_action' => 'retry_payment',
                'expires_in_seconds' => $expiresInSeconds,
                'is_waiting_payment' => false,
                'can_retry' => true,
            ];
        }

        if ($paymentMethod === 'credit_card') {
            return [
                'state' => 'processing',
                'tone' => 'warning',
                'payment_status_title' => 'Pagamento em analise',
                'order_status_label' => 'Pedido em analise',
                'payment_status_label' => 'Em analise',
                'payment_status_description' => $acquirerMessage
                    ?: 'Seu cartao foi enviado com seguranca e agora estamos aguardando a confirmacao do pagamento.',
                'next_action' => 'wait_payment_confirmation',
                'expires_in_seconds' => null,
                'is_waiting_payment' => true,
                'can_retry' => false,
            ];
        }

        if ($orderStatus === 'draft') {
            return [
                'state' => 'idle',
                'tone' => 'idle',
                'payment_status_title' => 'Finalize seu pagamento',
                'order_status_label' => 'Pedido iniciado',
                'payment_status_label' => 'Aguardando seus dados',
                'payment_status_description' => 'Escolha Pix ou cartao e conclua a compra para ativar o seu pacote.',
                'next_action' => 'continue_checkout',
                'expires_in_seconds' => $expiresInSeconds,
                'is_waiting_payment' => false,
                'can_retry' => false,
            ];
        }

        return [
            'state' => 'pending',
            'tone' => 'info',
            'payment_status_title' => 'Pix gerado com sucesso',
            'order_status_label' => 'Pedido criado',
            'payment_status_label' => 'Aguardando pagamento',
            'payment_status_description' => 'Use o QR Code ou o codigo copia e cola abaixo. A confirmacao aparece aqui automaticamente.',
            'next_action' => 'complete_payment',
            'expires_in_seconds' => $expiresInSeconds,
            'is_waiting_payment' => true,
            'can_retry' => false,
        ];
    }

    private function calculateExpiresInSeconds(?string $paymentExpiresAt): ?int
    {
        if (! $paymentExpiresAt) {
            return null;
        }

        $expiresAt = Carbon::parse($paymentExpiresAt);

        return max(0, now()->diffInSeconds($expiresAt, false));
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
