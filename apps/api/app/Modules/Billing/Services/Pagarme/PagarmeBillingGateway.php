<?php

namespace App\Modules\Billing\Services\Pagarme;

use App\Modules\Billing\Exceptions\BillingGatewayCheckoutFailedException;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Services\BillingGatewayInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PagarmeBillingGateway implements BillingGatewayInterface
{
    public function __construct(
        private readonly PagarmeClient $client,
        private readonly PagarmeCustomerNormalizer $customerNormalizer,
        private readonly PagarmeOrderPayloadFactory $payloadFactory,
        private readonly PagarmeStatusMapper $statusMapper,
    ) {}

    public function providerKey(): string
    {
        return 'pagarme';
    }

    public function createSubscriptionCheckout(BillingOrder $order, array $context = []): array
    {
        throw new RuntimeException('Pagar.me subscription checkout is not implemented in phase 1.');
    }

    public function createEventPackageCheckout(BillingOrder $order, array $context = []): array
    {
        $idempotencyKey = $order->idempotency_key ?: sprintf('billing-order:%s:attempt:1', $order->uuid);

        if ($order->idempotency_key !== $idempotencyKey) {
            $order->forceFill([
                'idempotency_key' => $idempotencyKey,
            ])->save();
        }

        try {
            $gatewayContext = $this->prepareCheckoutContext($order, $context, $idempotencyKey);
        } catch (BillingGatewayCheckoutFailedException $exception) {
            return $exception->checkout();
        }

        $response = $this->client->createOrder(
            $this->payloadFactory->build($order, $gatewayContext),
            $idempotencyKey,
        );

        $charge = (array) data_get($response, 'charges.0', []);
        $lastTransaction = (array) data_get($charge, 'last_transaction', []);

        return [
            'provider_key' => $this->providerKey(),
            'idempotency_key' => $idempotencyKey,
            'gateway_order_id' => $response['id'] ?? null,
            'gateway_charge_id' => $charge['id'] ?? null,
            'gateway_transaction_id' => $lastTransaction['id']
                ?? $lastTransaction['transaction_id']
                ?? null,
            'status' => $this->statusMapper->toBillingOrderStatus($response['status'] ?? $charge['status'] ?? null),
            'checkout_url' => data_get($lastTransaction, 'url') ?? null,
            'confirm_url' => null,
            'expires_at' => $lastTransaction['expires_at'] ?? null,
            'payment_method' => $charge['payment_method'] ?? data_get($context, 'payment.method') ?? $order->payment_method,
            'qr_code' => $lastTransaction['qr_code'] ?? null,
            'qr_code_url' => $lastTransaction['qr_code_url'] ?? null,
            'acquirer_message' => $lastTransaction['acquirer_message'] ?? null,
            'acquirer_return_code' => $lastTransaction['acquirer_return_code'] ?? null,
            'gateway_customer_id' => $gatewayContext['gateway_customer_id'] ?? ($response['customer']['id'] ?? null),
            'gateway_card_id' => $gatewayContext['gateway_card_id'] ?? data_get($lastTransaction, 'card.id'),
            'last_transaction' => $lastTransaction,
            'meta' => array_filter([
                'gateway_status' => $response['status'] ?? null,
                'charge_status' => $charge['status'] ?? null,
                'payment_method' => $charge['payment_method'] ?? null,
                'gateway_customer_id' => $gatewayContext['gateway_customer_id'] ?? ($response['customer']['id'] ?? null),
                'gateway_card_id' => $gatewayContext['gateway_card_id'] ?? data_get($lastTransaction, 'card.id'),
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
        ];
    }

    public function parseWebhook(array $payload, array $headers = []): array
    {
        $validator = Validator::make($payload, [
            'id' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', 'max:120'],
            'created_at' => ['nullable', 'date'],
            'data' => ['required', 'array'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $data = (array) $validated['data'];
        $rawType = (string) $validated['type'];
        $gatewayOrderId = data_get($data, 'id');
        $gatewayChargeId = data_get($data, 'charges.0.id');
        $billingOrderUuid = data_get($data, 'metadata.billing_order_uuid') ?? data_get($data, 'code');
        $gatewaySubscriptionId = data_get($data, 'subscription.id')
            ?? data_get($data, 'subscription_id')
            ?? data_get($data, 'invoice.subscription.id')
            ?? data_get($data, 'invoice.subscription_id')
            ?? data_get($data, 'metadata.gateway_subscription_id')
            ?? data_get($data, 'metadata.subscription_id');
        $gatewayInvoiceId = str_starts_with($rawType, 'invoice.')
            ? data_get($data, 'id')
            : (data_get($data, 'invoice.id')
                ?? data_get($data, 'invoice_id')
                ?? data_get($data, 'metadata.gateway_invoice_id'));
        $gatewayCycleId = data_get($data, 'cycle.id')
            ?? data_get($data, 'cycle_id')
            ?? data_get($data, 'invoice.cycle.id')
            ?? data_get($data, 'invoice.cycle_id')
            ?? data_get($data, 'metadata.gateway_cycle_id');
        $gatewayCustomerId = data_get($data, 'customer.id')
            ?? data_get($data, 'customer_id')
            ?? data_get($data, 'subscription.customer.id')
            ?? data_get($data, 'subscription.customer_id')
            ?? data_get($data, 'metadata.gateway_customer_id');

        if (str_starts_with($rawType, 'subscription.')) {
            $gatewaySubscriptionId = data_get($data, 'id')
                ?? data_get($data, 'subscription.id')
                ?? $gatewaySubscriptionId;
            $gatewayOrderId = data_get($data, 'order.id')
                ?? data_get($data, 'order_id')
                ?? null;
            $gatewayCustomerId = data_get($data, 'customer.id')
                ?? data_get($data, 'customer_id')
                ?? $gatewayCustomerId;
        }

        if (str_starts_with($rawType, 'charge.')) {
            $gatewayChargeId = data_get($data, 'id');
            $gatewayOrderId = data_get($data, 'order.id') ?? data_get($data, 'order_id');
            $billingOrderUuid = data_get($data, 'metadata.billing_order_uuid')
                ?? data_get($data, 'order.metadata.billing_order_uuid')
                ?? $billingOrderUuid;
            $gatewaySubscriptionId = data_get($data, 'subscription.id')
                ?? data_get($data, 'subscription_id')
                ?? data_get($data, 'invoice.subscription.id')
                ?? data_get($data, 'invoice.subscription_id')
                ?? data_get($data, 'metadata.gateway_subscription_id')
                ?? $gatewaySubscriptionId;
            $gatewayInvoiceId = data_get($data, 'invoice.id')
                ?? data_get($data, 'invoice_id')
                ?? data_get($data, 'metadata.gateway_invoice_id')
                ?? $gatewayInvoiceId;
            $gatewayCycleId = data_get($data, 'cycle.id')
                ?? data_get($data, 'cycle_id')
                ?? data_get($data, 'invoice.cycle.id')
                ?? data_get($data, 'invoice.cycle_id')
                ?? data_get($data, 'metadata.gateway_cycle_id')
                ?? $gatewayCycleId;
            $gatewayCustomerId = data_get($data, 'customer.id')
                ?? data_get($data, 'customer_id')
                ?? data_get($data, 'subscription.customer.id')
                ?? data_get($data, 'subscription.customer_id')
                ?? data_get($data, 'metadata.gateway_customer_id')
                ?? $gatewayCustomerId;
        }

        return [
            'provider_key' => $this->providerKey(),
            'event_key' => $validated['id'],
            'hook_id' => data_get($headers, 'X-Hook-Id') ?? data_get($headers, 'x-hook-id'),
            'event_type' => $this->statusMapper->toInternalWebhookType($rawType, $data),
            'billing_order_uuid' => $billingOrderUuid,
            'gateway_order_id' => $gatewayOrderId,
            'gateway_payment_id' => $gatewayChargeId,
            'gateway_charge_id' => $gatewayChargeId,
            'gateway_subscription_id' => $gatewaySubscriptionId,
            'gateway_invoice_id' => $gatewayInvoiceId,
            'gateway_cycle_id' => $gatewayCycleId,
            'gateway_customer_id' => $gatewayCustomerId,
            'gateway_transaction_id' => data_get($data, 'charges.0.last_transaction.id')
                ?? data_get($data, 'last_transaction.id'),
            'occurred_at' => isset($validated['created_at']) ? Carbon::parse($validated['created_at']) : now(),
            'payload' => $payload,
            'headers' => $headers,
        ];
    }

    public function cancelOrder(BillingOrder $order, array $context = []): array
    {
        $chargeId = $context['gateway_charge_id'] ?? $order->gateway_charge_id;

        if (! filled($chargeId)) {
            throw ValidationException::withMessages([
                'billing_order' => ['Nao foi possivel cancelar o pedido sem gateway_charge_id.'],
            ]);
        }

        $response = $this->client->cancelCharge((string) $chargeId);
        $lastTransaction = (array) data_get($response, 'last_transaction', []);

        return [
            'provider_key' => $this->providerKey(),
            'gateway_order_id' => $order->gateway_order_id,
            'gateway_charge_id' => $response['id'] ?? $chargeId,
            'gateway_transaction_id' => $lastTransaction['id'] ?? null,
            'status' => $this->statusMapper->toBillingOrderStatus($response['status'] ?? null),
            'last_transaction' => $lastTransaction ?: null,
            'gateway_response' => $response,
            'meta' => [
                'gateway_status' => $response['status'] ?? null,
            ],
        ];
    }

    private function prepareCheckoutContext(BillingOrder $order, array $context, string $idempotencyKey): array
    {
        $payment = (array) ($context['payment'] ?? data_get($order->metadata_json, 'payment', []));
        $method = $payment['method'] ?? $order->payment_method ?? 'pix';

        if ($method !== 'credit_card') {
            return $context;
        }

        $payer = (array) ($context['payer'] ?? $order->customer_snapshot_json ?? []);
        $gatewayCustomerId = $context['gateway_customer_id'] ?? null;
        $gatewayCardId = data_get($payment, 'credit_card.card_id') ?? ($context['gateway_card_id'] ?? null);

        if (! filled($gatewayCustomerId)) {
            $customer = $this->client->createCustomer($this->customerNormalizer->normalize($payer));
            $gatewayCustomerId = $customer['id'] ?? null;
        }

        if (! filled($gatewayCustomerId)) {
            throw new RuntimeException('Pagar.me did not return a customer id for the credit-card checkout.');
        }

        if (! filled($gatewayCardId)) {
            $cardToken = data_get($payment, 'credit_card.card_token');

            if (! filled($cardToken)) {
                throw ValidationException::withMessages([
                    'payment.credit_card.card_token' => ['Informe um card_token valido para checkout com cartao.'],
                ]);
            }

            $billingAddress = $this->customerNormalizer->normalizeBillingAddress(
                (array) data_get($payment, 'credit_card.billing_address', [])
            );

            try {
                $card = $this->client->createCustomerCard((string) $gatewayCustomerId, array_filter([
                    'token' => $cardToken,
                    'billing_address' => $billingAddress,
                ], fn (mixed $value): bool => $value !== null && $value !== [] && $value !== ''));
            } catch (RequestException $exception) {
                if ($exception->response?->status() === 412) {
                    throw new BillingGatewayCheckoutFailedException(
                        $this->buildCardCreationFailureCheckout(
                            $order,
                            $context,
                            $idempotencyKey,
                            (string) $gatewayCustomerId,
                            $exception,
                        ),
                        (string) (data_get($exception->response?->json() ?? [], 'message') ?? $exception->getMessage()),
                    );
                }

                throw $exception;
            }

            $gatewayCardId = $card['id'] ?? null;
        }

        if (! filled($gatewayCardId)) {
            throw new RuntimeException('Pagar.me did not return a card id for the credit-card checkout.');
        }

        return array_merge($context, [
            'gateway_customer_id' => (string) $gatewayCustomerId,
            'gateway_card_id' => (string) $gatewayCardId,
        ]);
    }

    private function buildCardCreationFailureCheckout(
        BillingOrder $order,
        array $context,
        string $idempotencyKey,
        string $gatewayCustomerId,
        RequestException $exception,
    ): array {
        $gatewayError = $exception->response?->json() ?? [];
        $message = data_get($gatewayError, 'message')
            ?? data_get($gatewayError, 'errors.0.message')
            ?? 'Could not create credit card.';
        $returnCode = data_get($gatewayError, 'errors.0.code');

        return [
            'provider_key' => $this->providerKey(),
            'idempotency_key' => $idempotencyKey,
            'gateway_order_id' => null,
            'gateway_charge_id' => null,
            'gateway_transaction_id' => null,
            'status' => 'failed',
            'checkout_url' => null,
            'confirm_url' => null,
            'expires_at' => null,
            'payment_method' => data_get($context, 'payment.method') ?? $order->payment_method ?? 'credit_card',
            'qr_code' => null,
            'qr_code_url' => null,
            'acquirer_message' => (string) $message,
            'acquirer_return_code' => is_scalar($returnCode) ? (string) $returnCode : null,
            'gateway_customer_id' => $gatewayCustomerId,
            'gateway_card_id' => null,
            'last_transaction' => null,
            'gateway_error' => $gatewayError,
            'meta' => array_filter([
                'gateway_status' => 'failed',
                'gateway_customer_id' => $gatewayCustomerId,
                'gateway_http_status' => $exception->response?->status(),
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
        ];
    }
}
