<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\BillingSubscriptionGatewayInterface;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateSubscriptionCardAction
{
    public function __construct(
        private readonly BillingSubscriptionGatewayInterface $subscriptionGateway,
        private readonly ProjectRecurringBillingStateAction $projectRecurringBillingState,
    ) {}

    public function execute(
        Organization $organization,
        array $payload,
        ?User $actor = null,
    ): Subscription {
        return DB::transaction(function () use ($organization, $payload, $actor) {
            /** @var Subscription|null $subscription */
            $subscription = Subscription::query()
                ->where('organization_id', $organization->id)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $subscription) {
                throw ValidationException::withMessages([
                    'subscription' => ['Nenhuma assinatura encontrada para a organizacao atual.'],
                ]);
            }

            $gatewayUpdate = $this->subscriptionGateway->updateSubscriptionCard($subscription, $payload);
            $projectionData = array_merge([
                'id' => $gatewayUpdate['gateway_subscription_id'] ?? $subscription->gateway_subscription_id,
                'status' => $subscription->contract_status ?: $subscription->status,
                'payment_method' => $subscription->payment_method ?: 'credit_card',
                'customer_id' => $gatewayUpdate['gateway_customer_id'] ?? $subscription->gateway_customer_id,
                'card' => array_filter([
                    'id' => $gatewayUpdate['gateway_card_id'] ?? $subscription->gateway_card_id,
                ]),
            ], (array) ($gatewayUpdate['gateway_response'] ?? []));

            $projected = $this->projectRecurringBillingState->execute([
                'provider_key' => $gatewayUpdate['provider_key'] ?? $subscription->gateway_provider ?? $this->subscriptionGateway->providerKey(),
                'event_type' => 'subscription.updated',
                'gateway_subscription_id' => $gatewayUpdate['gateway_subscription_id'] ?? $subscription->gateway_subscription_id,
                'gateway_customer_id' => $gatewayUpdate['gateway_customer_id'] ?? $subscription->gateway_customer_id,
                'occurred_at' => now(),
                'payload' => [
                    'id' => sprintf('subscription-card-update-%s', $subscription->id),
                    'type' => 'subscription.updated',
                    'created_at' => now()->toISOString(),
                    'data' => $projectionData,
                ],
            ]);

            $subscription = $subscription->fresh(['plan.features']);

            activity()
                ->performedOn($subscription)
                ->causedBy($actor)
                ->withProperties([
                    'organization_id' => $organization->id,
                    'gateway_subscription_id' => $subscription->gateway_subscription_id,
                    'gateway_card_id' => $gatewayUpdate['gateway_card_id'] ?? $subscription->gateway_card_id,
                    'gateway_customer_id' => $gatewayUpdate['gateway_customer_id'] ?? $subscription->gateway_customer_id,
                    'projection' => $projected,
                ])
                ->log('Cartao padrao da assinatura recorrente atualizado');

            return $subscription;
        });
    }
}
