<?php

namespace App\Modules\Billing\Services\Pagarme;

use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\BillingProfile;
use App\Modules\Billing\Models\Subscription;
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

    public function cancelSubscription(Subscription $subscription, array $context = []): array
    {
        if (! filled($subscription->gateway_subscription_id)) {
            throw ValidationException::withMessages([
                'subscription' => ['Nao foi possivel cancelar no provider sem gateway_subscription_id.'],
            ]);
        }

        $payload = [];

        if (array_key_exists('cancel_pending_invoices', $context)) {
            $payload['cancel_pending_invoices'] = (bool) $context['cancel_pending_invoices'];
        }

        $response = $this->client->cancelSubscription((string) $subscription->gateway_subscription_id, $payload);

        return [
            'provider_key' => $this->providerKey(),
            'gateway_subscription_id' => (string) $subscription->gateway_subscription_id,
            'gateway_status' => strtolower((string) ($response['status'] ?? 'canceled')),
            'gateway_response' => $response,
        ];
    }

    public function fetchSubscription(Subscription $subscription, array $context = []): array
    {
        if (! filled($subscription->gateway_subscription_id)) {
            throw ValidationException::withMessages([
                'subscription' => ['Nao foi possivel consultar a assinatura no provider sem gateway_subscription_id.'],
            ]);
        }

        return $this->client->getSubscription((string) $subscription->gateway_subscription_id);
    }

    public function listCycles(Subscription $subscription, array $query = []): array
    {
        if (! filled($subscription->gateway_subscription_id)) {
            return ['data' => []];
        }

        return $this->client->listSubscriptionCycles((string) $subscription->gateway_subscription_id, $query);
    }

    public function listInvoices(Subscription $subscription, array $query = []): array
    {
        if (! filled($subscription->gateway_subscription_id)) {
            return ['data' => []];
        }

        return $this->client->listInvoices(array_merge([
            'subscription_id' => (string) $subscription->gateway_subscription_id,
        ], $query));
    }

    public function listCharges(Subscription $subscription, array $query = []): array
    {
        $customerId = $this->resolveGatewayCustomerId($subscription);

        if (! filled($customerId)) {
            return ['data' => []];
        }

        return $this->client->listCharges(array_merge([
            'customer_id' => $customerId,
        ], $query));
    }

    public function getCharge(Subscription $subscription, string $chargeId, array $context = []): array
    {
        return $this->client->getCharge($chargeId);
    }

    public function listCustomerCards(Subscription $subscription, array $context = []): array
    {
        $customerId = $this->resolveGatewayCustomerId($subscription);

        if (! filled($customerId)) {
            return ['data' => []];
        }

        return $this->client->listCustomerCards($customerId);
    }

    public function updateSubscriptionCard(Subscription $subscription, array $context = []): array
    {
        if (! filled($subscription->gateway_subscription_id)) {
            throw ValidationException::withMessages([
                'subscription' => ['Nao foi possivel trocar o cartao sem gateway_subscription_id.'],
            ]);
        }

        $billingProfile = BillingProfile::query()->firstOrNew([
            'organization_id' => $subscription->organization_id,
        ]);
        $payload = $this->buildSubscriptionCardUpdatePayload($billingProfile, $context);
        $idempotencyKey = $context['idempotency_key'] ?? $this->makeCardUpdateIdempotencyKey($subscription, $payload);
        $response = $this->client->updateSubscriptionCard(
            (string) $subscription->gateway_subscription_id,
            $payload,
            $idempotencyKey,
        );
        $resolvedCardId = data_get($response, 'card.id')
            ?? data_get($payload, 'card_id');
        $resolvedCustomerId = $this->resolveGatewayCustomerId($subscription) ?: $billingProfile->gateway_customer_id;

        $this->persistBillingProfile(
            $billingProfile,
            $this->payerSnapshotFromProfile($billingProfile),
            $resolvedCustomerId,
            filled($resolvedCardId) ? (string) $resolvedCardId : null,
        );

        return [
            'provider_key' => $this->providerKey(),
            'gateway_subscription_id' => (string) $subscription->gateway_subscription_id,
            'gateway_customer_id' => $resolvedCustomerId,
            'gateway_card_id' => filled($resolvedCardId) ? (string) $resolvedCardId : null,
            'idempotency_key' => $idempotencyKey,
            'gateway_response' => $response,
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

    private function resolveGatewayCustomerId(Subscription $subscription): ?string
    {
        if (filled($subscription->gateway_customer_id)) {
            return (string) $subscription->gateway_customer_id;
        }

        $billingProfile = BillingProfile::query()
            ->where('organization_id', $subscription->organization_id)
            ->first();

        return filled($billingProfile?->gateway_customer_id)
            ? (string) $billingProfile->gateway_customer_id
            : null;
    }

    private function buildSubscriptionCardUpdatePayload(BillingProfile $billingProfile, array $context): array
    {
        $cardId = data_get($context, 'card_id');
        $cardToken = data_get($context, 'card_token');

        if (filled($cardId)) {
            return [
                'card_id' => (string) $cardId,
            ];
        }

        if (! filled($cardToken)) {
            throw ValidationException::withMessages([
                'card_token' => ['Informe um card_id salvo ou gere um novo card_token valido para trocar o cartao.'],
            ]);
        }

        $billingAddress = $this->customerNormalizer->normalizeBillingAddress(
            (array) (data_get($context, 'billing_address')
                ?? $billingProfile->billing_address_json
                ?? [])
        );

        return [
            'card' => array_filter([
                'token' => (string) $cardToken,
                'billing_address' => $billingAddress,
            ], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []),
        ];
    }

    private function payerSnapshotFromProfile(BillingProfile $billingProfile): array
    {
        return array_filter([
            'name' => $billingProfile->payer_name,
            'email' => $billingProfile->payer_email,
            'document' => $billingProfile->payer_document,
            'phone' => $billingProfile->payer_phone,
            'address' => $billingProfile->billing_address_json ?? [],
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function makeCardUpdateIdempotencyKey(Subscription $subscription, array $payload): string
    {
        return sprintf(
            'subscription-card:%s:%s',
            $subscription->gateway_subscription_id ?: $subscription->id,
            substr(sha1($this->normalizePayloadForHash($payload)), 0, 24)
        );
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
