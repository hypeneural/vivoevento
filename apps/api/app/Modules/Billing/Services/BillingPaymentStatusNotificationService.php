<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Enums\BillingOrderMode;
use App\Modules\Billing\Enums\BillingOrderNotificationType;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\BillingOrderNotification;
use App\Modules\Billing\Models\Payment;
use App\Modules\WhatsApp\Clients\DTOs\SendTextData;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\WhatsAppMessagingService;
use App\Shared\Support\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingPaymentStatusNotificationService
{
    public function __construct(
        private readonly BillingWhatsAppInstanceResolver $instanceResolver,
        private readonly WhatsAppMessagingService $messagingService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function queuePixGenerated(BillingOrder $billingOrder): array
    {
        return $this->queue($billingOrder, BillingOrderNotificationType::PixGenerated);
    }

    /**
     * @return array<string, mixed>
     */
    public function queuePaymentPaid(BillingOrder $billingOrder): array
    {
        return $this->queue($billingOrder, BillingOrderNotificationType::PaymentPaid);
    }

    /**
     * @return array<string, mixed>
     */
    public function queuePaymentFailed(BillingOrder $billingOrder): array
    {
        return $this->queue($billingOrder, BillingOrderNotificationType::PaymentFailed);
    }

    /**
     * @return array<string, mixed>
     */
    public function queuePaymentRefunded(BillingOrder $billingOrder): array
    {
        return $this->queue($billingOrder, BillingOrderNotificationType::PaymentRefunded);
    }

    /**
     * @return array<string, mixed>
     */
    private function queue(BillingOrder $billingOrder, BillingOrderNotificationType $type): array
    {
        if (! config('billing.payment_notifications.enabled', true)) {
            return [
                'requested' => false,
                'duplicate' => false,
                'status' => 'disabled',
                'notification_type' => $type->value,
            ];
        }

        if ($billingOrder->mode?->value !== BillingOrderMode::EventPackage->value) {
            return [
                'requested' => false,
                'duplicate' => false,
                'status' => 'not_applicable',
                'notification_type' => $type->value,
            ];
        }

        if ($type === BillingOrderNotificationType::PixGenerated && $billingOrder->payment_method !== 'pix') {
            return [
                'requested' => false,
                'duplicate' => false,
                'status' => 'not_applicable',
                'notification_type' => $type->value,
            ];
        }

        return DB::transaction(function () use ($billingOrder, $type) {
            /** @var BillingOrder $order */
            $order = BillingOrder::query()
                ->with(['event.organization', 'buyer', 'items', 'payments'])
                ->lockForUpdate()
                ->findOrFail($billingOrder->id);

            $notification = BillingOrderNotification::query()->firstOrNew([
                'billing_order_id' => $order->id,
                'notification_type' => $type->value,
                'channel' => 'whatsapp',
            ]);

            if ($notification->exists) {
                return $this->serializeNotification($notification->fresh(), duplicate: true);
            }

            $notification->fill([
                'status' => 'pending',
                'recipient_phone' => $this->resolveTargetPhone($order),
                'context_json' => $this->buildContext($order, $type),
            ]);
            $notification->save();

            $dispatch = fn () => $this->deliver($notification->id);

            if (DB::transactionLevel() > 0) {
                DB::afterCommit($dispatch);
            } else {
                $dispatch();
            }

            return $this->serializeNotification($notification->fresh(), duplicate: false);
        });
    }

    private function deliver(int $notificationId): void
    {
        DB::transaction(function () use ($notificationId) {
            /** @var BillingOrderNotification|null $notification */
            $notification = BillingOrderNotification::query()
                ->with(['order.event.organization', 'order.buyer', 'order.items', 'order.payments'])
                ->lockForUpdate()
                ->find($notificationId);

            if (! $notification || $notification->status !== 'pending') {
                return;
            }

            $order = $notification->order;

            if (! $order) {
                $notification->forceFill([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'context_json' => array_merge($notification->context_json ?? [], [
                        'delivery' => [
                            'reason' => 'missing_billing_order',
                        ],
                    ]),
                ])->save();

                return;
            }

            $phone = $notification->recipient_phone ?: $this->resolveTargetPhone($order);

            if (! $phone) {
                $notification->forceFill([
                    'status' => 'skipped',
                    'context_json' => array_merge($notification->context_json ?? [], [
                        'delivery' => [
                            'reason' => 'missing_target_phone',
                        ],
                    ]),
                ])->save();

                return;
            }

            $instance = $this->instanceResolver->resolve(
                configuredInstanceId: $this->configuredInstanceId(),
                allowSingleConnectedFallback: $this->allowSingleConnectedFallback(),
            );

            if (! $instance) {
                $notification->forceFill([
                    'status' => 'unavailable',
                    'recipient_phone' => $phone,
                    'context_json' => array_merge($notification->context_json ?? [], [
                        'delivery' => [
                            'reason' => 'whatsapp_instance_unavailable',
                        ],
                    ]),
                ])->save();

                return;
            }

            try {
                $message = $this->messagingService->sendText(
                    $instance,
                    new SendTextData(
                        phone: $phone,
                        message: $this->buildMessage($order, $notification->notification_type),
                    ),
                );

                $this->attachBillingContextToMessage($message, $order, $notification);

                $notification->forceFill([
                    'status' => 'queued',
                    'recipient_phone' => $phone,
                    'whatsapp_instance_id' => $instance->id,
                    'whatsapp_message_id' => $message->id,
                    'dispatched_at' => now(),
                    'context_json' => array_merge($notification->context_json ?? [], [
                        'delivery' => [
                            'reason' => null,
                            'message_id' => $message->id,
                            'instance_id' => $instance->id,
                        ],
                    ]),
                ])->save();
            } catch (\Throwable $exception) {
                Log::warning('Billing payment status notification failed.', [
                    'billing_order_id' => $order->id,
                    'billing_order_uuid' => $order->uuid,
                    'notification_type' => $notification->notification_type?->value,
                    'target_phone' => $phone,
                    'error' => $exception->getMessage(),
                ]);

                $notification->forceFill([
                    'status' => 'failed',
                    'recipient_phone' => $phone,
                    'failed_at' => now(),
                    'context_json' => array_merge($notification->context_json ?? [], [
                        'delivery' => [
                            'reason' => 'dispatch_failed',
                            'error' => $exception->getMessage(),
                        ],
                    ]),
                ])->save();
            }
        });
    }

    private function attachBillingContextToMessage(
        WhatsAppMessage $message,
        BillingOrder $order,
        BillingOrderNotification $notification,
    ): void {
        $payload = $message->payload_json ?? [];
        $payload['context'] = array_filter([
            'module' => 'billing',
            'billing_order_id' => $order->id,
            'billing_order_uuid' => $order->uuid,
            'notification_type' => $notification->notification_type?->value,
            'event_id' => $order->event_id,
        ], fn (mixed $value): bool => $value !== null);

        $message->forceFill([
            'payload_json' => $payload,
        ])->save();
    }

    private function resolveTargetPhone(BillingOrder $order): ?string
    {
        $phone = data_get($order->customer_snapshot_json, 'phone')
            ?: $order->buyer?->phone;

        return PhoneNumber::normalizeBrazilianWhatsAppOrNull($phone);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(BillingOrder $order, BillingOrderNotificationType $type): array
    {
        $payment = $order->payments->sortByDesc('id')->first();
        $gatewayMetadata = (array) data_get($order->metadata_json, 'gateway', []);

        return array_filter([
            'billing_order_id' => $order->id,
            'billing_order_uuid' => $order->uuid,
            'notification_type' => $type->value,
            'payment_method' => $order->payment_method,
            'billing_status' => $order->status?->value,
            'gateway_status' => $order->gateway_status,
            'event_id' => $order->event_id,
            'event_title' => $order->event?->title,
            'amount_cents' => $order->total_cents,
            'expires_at' => $payment?->expires_at?->toISOString() ?? $order->expires_at?->toISOString(),
            'qr_code_url' => $payment?->qr_code_url ?? ($gatewayMetadata['qr_code_url'] ?? null),
            'acquirer_message' => $payment?->acquirer_message ?? ($gatewayMetadata['acquirer_message'] ?? null),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function buildMessage(BillingOrder $order, BillingOrderNotificationType $type): string
    {
        return match ($type) {
            BillingOrderNotificationType::PixGenerated => $this->buildPixGeneratedMessage($order),
            BillingOrderNotificationType::PaymentPaid => $this->buildPaymentPaidMessage($order),
            BillingOrderNotificationType::PaymentFailed => $this->buildPaymentFailedMessage($order),
            BillingOrderNotificationType::PaymentRefunded => $this->buildPaymentRefundedMessage($order),
        };
    }

    private function buildPixGeneratedMessage(BillingOrder $order): string
    {
        $payment = $this->latestPayment($order);
        $expiresAt = $payment?->expires_at ?? $order->expires_at;
        $qrCode = $payment?->qr_code ?? data_get($order->metadata_json, 'gateway.qr_code');
        $qrCodeUrl = $payment?->qr_code_url ?? data_get($order->metadata_json, 'gateway.qr_code_url');

        $lines = [
            'Evento Vivo',
            '',
            "O PIX do pedido para *{$this->eventTitle($order)}* foi gerado.",
            "Valor: {$this->formatMoney($order->total_cents, $order->currency)}",
        ];

        if ($expiresAt instanceof Carbon) {
            $lines[] = 'Expira em: '.$expiresAt->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i');
        }

        if ($qrCodeUrl) {
            $lines[] = "QR Code: {$qrCodeUrl}";
        }

        if ($qrCode) {
            $lines[] = '';
            $lines[] = 'Codigo PIX copia e cola:';
            $lines[] = $qrCode;
        }

        return implode("\n", $lines);
    }

    private function buildPaymentPaidMessage(BillingOrder $order): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $eventUrl = $order->event_id ? "{$frontendUrl}/events/{$order->event_id}" : "{$frontendUrl}/events";
        $loginUrl = "{$frontendUrl}/login";

        return implode("\n", [
            'Evento Vivo',
            '',
            "Pagamento confirmado para *{$this->eventTitle($order)}*.",
            "Valor: {$this->formatMoney($order->total_cents, $order->currency)}",
            "Pacote: {$this->packageLabel($order)}",
            '',
            "Acesse seu painel: {$eventUrl}",
            "Login: {$loginUrl}",
        ]);
    }

    private function buildPaymentFailedMessage(BillingOrder $order): string
    {
        $payment = $this->latestPayment($order);
        $reason = $payment?->acquirer_message
            ?? data_get($order->metadata_json, 'gateway.acquirer_message')
            ?? 'A transacao nao foi autorizada.';

        return implode("\n", [
            'Evento Vivo',
            '',
            "Pagamento reprovado para *{$this->eventTitle($order)}*.",
            "Valor: {$this->formatMoney($order->total_cents, $order->currency)}",
            "Motivo: {$reason}",
            '',
            'Voce pode tentar novamente no checkout com outro meio de pagamento.',
        ]);
    }

    private function buildPaymentRefundedMessage(BillingOrder $order): string
    {
        return implode("\n", [
            'Evento Vivo',
            '',
            "O pagamento de *{$this->eventTitle($order)}* foi estornado.",
            "Valor: {$this->formatMoney($order->total_cents, $order->currency)}",
            '',
            'Se precisar de ajuda, responda esta mensagem para o nosso atendimento.',
        ]);
    }

    private function latestPayment(BillingOrder $order): ?Payment
    {
        return $order->payments->sortByDesc('id')->first();
    }

    private function eventTitle(BillingOrder $order): string
    {
        return $order->event?->title
            ?? data_get($order->metadata_json, 'event.title')
            ?? 'seu evento';
    }

    private function packageLabel(BillingOrder $order): string
    {
        $item = $order->items->first();

        return data_get($item?->snapshot_json, 'package.name')
            ?? $item?->description
            ?? 'Pacote Evento Vivo';
    }

    private function formatMoney(int $amountCents, string $currency): string
    {
        $prefix = strtoupper($currency) === 'BRL' ? 'R$' : strtoupper($currency);

        return sprintf('%s %s', $prefix, number_format($amountCents / 100, 2, ',', '.'));
    }

    private function configuredInstanceId(): ?int
    {
        $value = config('billing.payment_notifications.whatsapp_instance_id');

        return is_numeric($value) ? (int) $value : null;
    }

    private function allowSingleConnectedFallback(): bool
    {
        return (bool) config('billing.payment_notifications.allow_single_connected_fallback', true);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeNotification(?BillingOrderNotification $notification, bool $duplicate): array
    {
        return [
            'requested' => $notification !== null,
            'duplicate' => $duplicate,
            'notification_id' => $notification?->id,
            'notification_type' => $notification?->notification_type?->value,
            'status' => $notification?->status,
            'recipient_phone' => $notification?->recipient_phone,
            'whatsapp_message_id' => $notification?->whatsapp_message_id,
        ];
    }
}
