<?php

namespace App\Modules\Billing\Services\Pagarme;

class PagarmeStatusMapper
{
    public function toBillingOrderStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'paid' => 'paid',
            'failed' => 'failed',
            'canceled', 'cancelled' => 'canceled',
            'refunded', 'partial_canceled', 'partially_refunded', 'chargedback' => 'refunded',
            default => 'pending_payment',
        };
    }

    public function toPaymentStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'paid' => 'paid',
            'failed', 'canceled', 'cancelled', 'refused', 'not_authorized' => 'failed',
            'refunded', 'partial_canceled', 'partially_refunded', 'chargedback' => 'refunded',
            default => 'pending',
        };
    }

    public function toInternalWebhookType(string $type): string
    {
        return match ($type) {
            'order.paid', 'charge.paid' => 'payment.paid',
            'order.payment_failed', 'charge.payment_failed' => 'payment.failed',
            'charge.refunded' => 'payment.refunded',
            'charge.partial_canceled' => 'payment.partially_refunded',
            'charge.chargedback' => 'payment.chargeback',
            'order.canceled' => 'checkout.canceled',
            default => 'unsupported',
        };
    }
}
