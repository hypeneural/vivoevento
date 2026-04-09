<?php

namespace App\Modules\Billing\Services\Pagarme;

use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\BillingProfile;
use App\Modules\Billing\Services\BillingSubscriptionGatewayInterface;
use App\Modules\Plans\Models\Plan;
use App\Modules\Plans\Models\PlanPrice;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PagarmeSubscriptionGatewayService implements BillingSubscriptionGatewayInterface
{
    public function __construct(
        private readonly PagarmeClient $client,
        private readonly PagarmeCustomerNormalizer $customerNormalizer,
        private readonly PagarmePlanPayloadFactory $planPayloadFactory,
        private readonly PagarmeSubscriptionPayloadFactory $subscriptionPayloadFactory,
    ) {}

    public function providerKey(): string
    {
        return 'pagarme';
    }

    public function ensurePlan(Plan $plan, PlanPrice $planPrice, array $context = []): array
    {
        if (filled($planPrice->gateway_plan_id)) {
            return [
                'provider_key' => $this->providerKey(),
                'gateway_plan_id' => (string) $planPrice->gateway_plan_id,
                'created' => false,
                'idempotency_key' => null,
                'payload' => $planPrice->gateway_plan_payload_json ?? [],
            ];
        }

        $payload = $this->planPayloadFactory->build($plan, $planPrice, $context);
        $idempotencyKey = $context['idempotency_key'] ?? $this->makePlanIdempotencyKey($planPrice, $payload);
        $response = $this->client->createPlan($payload, $idempotencyKey);
        $gatewayPlanId = $response['id'] ?? null;

        if (! filled($gatewayPlanId)) {
            throw new RuntimeException('Pagar.me did not return a plan id while creating the recurring plan.');
        }

        $planPrice->forceFill([
            'gateway_provider' => $planPrice->gateway_provider ?: $this->providerKey(),
            'gateway_plan_id' => (string) $gatewayPlanId,
            'gateway_plan_payload_json' => $payload,
        ])->save();

        return [
            'provider_key' => $this->providerKey(),
            'gateway_plan_id' => (string) $gatewayPlanId,
            'created' => true,
            'idempotency_key' => $idempotencyKey,
            'payload' => $payload,
            'response' => $response,
        ];
    }

    public function createSubscription(BillingOrder $order, Plan $plan, PlanPrice $planPrice, array $context = []): array
    {
        $payer = (array) ($context['payer'] ?? $order->customer_snapshot_json ?? []);

        if ($payer === []) {
            throw ValidationException::withMessages([
                'payer' => ['Informe os dados do pagador para criar a assinatura recorrente.'],
            ]);
        }

        $billingProfile = BillingProfile::query()->firstOrNew([
            'organization_id' => $order->organization_id,
        ]);

        $gatewayCustomerId = $this->resolveCustomerId($billingProfile, $payer);
        $gatewayCardId = $this->resolveCardId(
            $billingProfile,
            $gatewayCustomerId,
            (string) ($context['payment_method'] ?? $order->payment_method ?? 'credit_card'),
            $payer,
            $context,
        );

        $payload = $this->subscriptionPayloadFactory->build($order, $plan, $planPrice, array_merge($context, [
            'gateway_plan_id' => $context['gateway_plan_id'] ?? $planPrice->gateway_plan_id,
            'gateway_customer_id' => $gatewayCustomerId,
            'gateway_card_id' => $gatewayCardId,
            'payer' => $payer,
        ]));

        $idempotencyKey = $order->idempotency_key ?: $this->makeSubscriptionIdempotencyKey($order, $payload);

        if ($order->idempotency_key !== $idempotencyKey) {
            $order->forceFill([
                'idempotency_key' => $idempotencyKey,
            ])->save();
        }

        $response = $this->client->createSubscription($payload, $idempotencyKey);
        $gatewaySubscriptionId = $response['id'] ?? null;

        if (! filled($gatewaySubscriptionId)) {
            throw new RuntimeException('Pagar.me did not return a subscription id while creating the recurring subscription.');
        }

        $resolvedCardId = data_get($response, 'card.id') ?? $gatewayCardId;

        $this->persistBillingProfile(
            $billingProfile,
            $payer,
            $gatewayCustomerId,
            filled($resolvedCardId) ? (string) $resolvedCardId : null,
        );

        $gatewayStatus = strtolower((string) ($response['status'] ?? ''));
        $contractStatus = $this->mapContractStatus($gatewayStatus);
        $startsAt = $this->parseGatewayDate($response['start_at'] ?? null);
        $nextBillingAt = $this->parseGatewayDate($response['next_billing_at'] ?? null);
        $currentPeriodStartedAt = $this->parseGatewayDate(
            data_get($response, 'current_period.start_at') ?? data_get($response, 'current_period_started_at')
        );
        $currentPeriodEndsAt = $this->parseGatewayDate(
            data_get($response, 'current_period.end_at') ?? data_get($response, 'current_period_end_at')
        );

        return [
            'provider_key' => $this->providerKey(),
            'idempotency_key' => $idempotencyKey,
            'gateway_plan_id' => (string) ($context['gateway_plan_id'] ?? $planPrice->gateway_plan_id),
            'gateway_subscription_id' => (string) $gatewaySubscriptionId,
            'gateway_customer_id' => (string) $gatewayCustomerId,
            'gateway_card_id' => filled($resolvedCardId) ? (string) $resolvedCardId : null,
            'gateway_status' => $gatewayStatus !== '' ? $gatewayStatus : null,
            'gateway_status_reason' => data_get($response, 'status_reason'),
            'status' => $contractStatus,
            'contract_status' => $contractStatus,
            'billing_status' => 'pending',
            'access_status' => $contractStatus === 'active' ? 'enabled' : 'provisioning',
            'payment_method' => (string) ($context['payment_method'] ?? $order->payment_method ?? 'credit_card'),
            'billing_type' => $planPrice->billing_type ?: 'prepaid',
            'starts_at' => $startsAt,
            'next_billing_at' => $nextBillingAt,
            'current_period_started_at' => $currentPeriodStartedAt,
            'current_period_ends_at' => $currentPeriodEndsAt,
            'gateway_response' => $response,
            'billing_profile' => $billingProfile->fresh(),
        ];
    }

    private function resolveCustomerId(BillingProfile $billingProfile, array $payer): string
    {
        if (filled($billingProfile->gateway_customer_id)) {
            return (string) $billingProfile->gateway_customer_id;
        }

        $customer = $this->client->createCustomer($this->customerNormalizer->normalize($payer));
        $gatewayCustomerId = $customer['id'] ?? null;

        if (! filled($gatewayCustomerId)) {
            throw new RuntimeException('Pagar.me did not return a customer id for the recurring subscription.');
        }

        return (string) $gatewayCustomerId;
    }

    private function resolveCardId(
        BillingProfile $billingProfile,
        string $gatewayCustomerId,
        string $paymentMethod,
        array $payer,
        array $context = [],
    ): ?string {
        if ($paymentMethod !== 'credit_card') {
            return null;
        }

        $explicitCardId = data_get($context, 'credit_card.card_id') ?? data_get($context, 'gateway_card_id');

        if (filled($explicitCardId)) {
            return (string) $explicitCardId;
        }

        $cardToken = data_get($context, 'credit_card.card_token');

        if (! filled($cardToken) && filled($billingProfile->gateway_default_card_id)) {
            return (string) $billingProfile->gateway_default_card_id;
        }

        if (! filled($cardToken)) {
            throw ValidationException::withMessages([
                'credit_card.card_token' => ['Informe um card_token valido para a assinatura recorrente.'],
            ]);
        }

        $billingAddress = $this->customerNormalizer->normalizeBillingAddress(
            (array) (data_get($context, 'credit_card.billing_address') ?? ($payer['address'] ?? []))
        );

        $card = $this->client->createCustomerCard($gatewayCustomerId, array_filter([
            'token' => $cardToken,
            'billing_address' => $billingAddress,
        ], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []));

        $gatewayCardId = $card['id'] ?? null;

        if (! filled($gatewayCardId)) {
            throw new RuntimeException('Pagar.me did not return a card id for the recurring subscription.');
        }

        return (string) $gatewayCardId;
    }

    private function persistBillingProfile(
        BillingProfile $billingProfile,
        array $payer,
        ?string $gatewayCustomerId,
        ?string $gatewayCardId,
    ): void {
        $billingProfile->forceFill([
            'gateway_provider' => $this->providerKey(),
            'gateway_customer_id' => $gatewayCustomerId ?: $billingProfile->gateway_customer_id,
            'gateway_default_card_id' => $gatewayCardId ?: $billingProfile->gateway_default_card_id,
            'payer_name' => $payer['name'] ?? $billingProfile->payer_name,
            'payer_email' => $payer['email'] ?? $billingProfile->payer_email,
            'payer_document' => $payer['document'] ?? $billingProfile->payer_document,
            'payer_phone' => $payer['phone'] ?? $billingProfile->payer_phone,
            'billing_address_json' => (array) ($payer['address'] ?? $billingProfile->billing_address_json ?? []),
            'metadata_json' => array_merge($billingProfile->metadata_json ?? [], [
                'last_synced_at' => now()->toISOString(),
            ]),
        ])->save();
    }

    private function mapContractStatus(string $gatewayStatus): string
    {
        return match ($gatewayStatus) {
            'active' => 'active',
            'future' => 'future',
            'canceled', 'cancelled' => 'canceled',
            default => 'pending_activation',
        };
    }

    private function parseGatewayDate(mixed $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        return Carbon::parse((string) $value);
    }

    private function makePlanIdempotencyKey(PlanPrice $planPrice, array $payload): string
    {
        return sprintf(
            'gateway-plan:%s:%s',
            $planPrice->id,
            substr(sha1($this->normalizePayloadForHash($payload)), 0, 24)
        );
    }

    private function makeSubscriptionIdempotencyKey(BillingOrder $order, array $payload): string
    {
        return sprintf(
            'subscription-checkout:%s:%s',
            $order->uuid,
            substr(sha1($this->normalizePayloadForHash($payload)), 0, 24)
        );
    }

    private function normalizePayloadForHash(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
