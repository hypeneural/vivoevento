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
            'chargedback' => 'chargedback',
            'refunded', 'partial_canceled', 'partially_refunded' => 'refunded',
            default => 'pending',
        };
    }

    public function toInternalWebhookType(string $type, array $data = []): string
    {
        if (str_starts_with($type, 'subscription.') || str_starts_with($type, 'invoice.')) {
            return $type;
        }

        if (str_starts_with($type, 'charge.') && $this->isRecurringChargePayload($data)) {
            return $type;
        }

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

    public function toRecurringContractStatus(?string $status, ?string $chargeStatus = null): string
    {
        if (strtolower((string) $chargeStatus) === 'chargedback') {
            return 'canceled';
        }

        return match (strtolower((string) $status)) {
            'active' => 'active',
            'future' => 'future',
            'trialing', 'trial' => 'trialing',
            'canceled', 'cancelled' => 'canceled',
            default => 'pending_activation',
        };
    }

    public function toInvoiceStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'paid' => 'paid',
            'failed' => 'failed',
            'canceled', 'cancelled' => 'canceled',
            'refunded', 'partial_canceled', 'partially_refunded' => 'refunded',
            default => 'open',
        };
    }

    public function toRecurringBillingStatus(?string $invoiceStatus = null, ?string $chargeStatus = null): string
    {
        $invoiceStatus = strtolower((string) $invoiceStatus);
        $chargeStatus = strtolower((string) $chargeStatus);

        if ($chargeStatus === 'chargedback') {
            return 'chargedback';
        }

        if (in_array($chargeStatus, ['refunded', 'partial_canceled', 'partially_refunded'], true) || $invoiceStatus === 'refunded') {
            return 'refunded';
        }

        if ($chargeStatus === 'paid' || $invoiceStatus === 'paid') {
            return 'paid';
        }

        if (in_array($chargeStatus, ['failed', 'refused', 'not_authorized'], true) || $invoiceStatus === 'failed') {
            return 'grace_period';
        }

        if (
            in_array($invoiceStatus, ['pending', 'scheduled', 'processing', 'open'], true)
            || in_array($chargeStatus, ['pending', 'processing', 'waiting_payment'], true)
        ) {
            return 'pending';
        }

        return 'pending';
    }

    public function toRecurringAccessStatus(string $contractStatus, string $billingStatus, bool $hasFutureAccess = false): string
    {
        if ($billingStatus === 'chargedback') {
            return 'disabled';
        }

        if ($contractStatus === 'canceled') {
            return $hasFutureAccess ? 'enabled' : 'disabled';
        }

        return match ($billingStatus) {
            'grace_period' => 'grace_period',
            default => in_array($contractStatus, ['future', 'pending_activation'], true)
                ? 'provisioning'
                : 'enabled',
        };
    }

    private function isRecurringChargePayload(array $data): bool
    {
        return filled(data_get($data, 'subscription_id'))
            || filled(data_get($data, 'subscription.id'))
            || filled(data_get($data, 'invoice.id'))
            || filled(data_get($data, 'invoice_id'))
            || filled(data_get($data, 'metadata.gateway_subscription_id'))
            || filled(data_get($data, 'metadata.subscription_id'));
    }
}
