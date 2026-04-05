<?php

namespace App\Modules\Billing\Services\Pagarme;

use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\BillingOrderItem;
use InvalidArgumentException;

class PagarmeOrderPayloadFactory
{
    public function __construct(
        private readonly PagarmeCustomerNormalizer $customerNormalizer,
    ) {}

    public function build(BillingOrder $order, array $context = []): array
    {
        $order->loadMissing('items');

        $payment = (array) ($context['payment'] ?? data_get($order->metadata_json, 'payment', []));
        $payer = (array) ($context['payer'] ?? $order->customer_snapshot_json ?? []);
        $method = $payment['method'] ?? $order->payment_method ?? 'pix';
        $gatewayCustomerId = $context['gateway_customer_id'] ?? null;

        return array_filter([
            'code' => $order->uuid,
            'closed' => true,
            'items' => $order->items->map(fn (BillingOrderItem $item) => $this->buildItemPayload($order, $item))->values()->all(),
            'customer_id' => filled($gatewayCustomerId) ? (string) $gatewayCustomerId : null,
            'customer' => filled($gatewayCustomerId) ? null : $this->customerNormalizer->normalize($payer),
            'payments' => [
                $this->buildPaymentPayload($method, $payment, $payer, $context),
            ],
            'metadata' => array_filter([
                'billing_order_uuid' => $order->uuid,
                'billing_order_id' => $order->id,
                'event_id' => $order->event_id,
                'organization_id' => $order->organization_id,
                'package_id' => data_get($order->metadata_json, 'package_id'),
                'journey' => data_get($order->metadata_json, 'journey'),
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
        ], fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }

    private function buildItemPayload(BillingOrder $order, BillingOrderItem $item): array
    {
        return array_filter([
            'code' => data_get($item->snapshot_json, 'package.code')
                ?? data_get($order->metadata_json, 'package_code')
                ?? (string) $item->reference_id,
            'amount' => (int) $item->unit_amount_cents,
            'description' => $item->description,
            'quantity' => (int) $item->quantity,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function buildPaymentPayload(string $method, array $payment, array $payer, array $context = []): array
    {
        return match ($method) {
            'credit_card' => $this->buildCreditCardPayload($payment, $context),
            'pix' => $this->buildPixPayload($payment, $payer),
            default => throw new InvalidArgumentException("Unsupported Pagar.me payment method [{$method}]."),
        };
    }

    private function buildPixPayload(array $payment, array $payer): array
    {
        $expiresIn = (int) (data_get($payment, 'pix.expires_in') ?: config('services.pagarme.pix_expires_in', 1800));

        return [
            'payment_method' => 'pix',
            'pix' => array_filter([
                'expires_in' => $expiresIn,
                'additional_information' => array_values(array_filter([
                    [
                        'name' => 'Pedido',
                        'value' => data_get($payer, 'name'),
                    ],
                ], fn (array $item): bool => filled($item['value'] ?? null))),
            ], fn (mixed $value): bool => $value !== null && $value !== []),
        ];
    }

    private function buildCreditCardPayload(array $payment, array $context = []): array
    {
        $cardToken = data_get($payment, 'credit_card.card_token');
        $cardId = $context['gateway_card_id'] ?? data_get($payment, 'credit_card.card_id');

        return [
            'payment_method' => 'credit_card',
            'credit_card' => array_filter([
                'installments' => (int) (data_get($payment, 'credit_card.installments') ?: 1),
                'statement_descriptor' => data_get($payment, 'credit_card.statement_descriptor')
                    ?: config('services.pagarme.statement_descriptor', 'EVENTOVIVO'),
                'operation_type' => 'auth_and_capture',
                'card_token' => blank($cardId) && filled($cardToken) ? $cardToken : null,
                'card_id' => filled($cardId) ? $cardId : null,
                'billing_address' => $this->customerNormalizer->normalizeBillingAddress(
                    (array) data_get($payment, 'credit_card.billing_address', [])
                ),
            ], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []),
        ];
    }
}
