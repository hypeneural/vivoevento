<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\BillingOrderMode;
use App\Modules\Billing\Enums\BillingOrderStatus;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Services\BillingGatewayManager;
use App\Modules\Billing\Services\BillingSubscriptionGatewayInterface;
use App\Modules\Billing\Services\PlanSnapshotService;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Plans\Models\Plan;
use App\Modules\Plans\Models\PlanPrice;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateSubscriptionCheckoutAction
{
    public function __construct(
        private readonly PlanSnapshotService $planSnapshots,
        private readonly BillingGatewayManager $gatewayManager,
        private readonly BillingSubscriptionGatewayInterface $subscriptionGateway,
        private readonly RegisterBillingGatewayPaymentAction $registerBillingGatewayPayment,
    ) {}

    public function execute(
        Organization $organization,
        User $buyer,
        Plan $plan,
        string $billingCycle = 'monthly',
        array $checkoutData = [],
    ): array {
        $snapshot = $this->planSnapshots->build($plan, $billingCycle);
        $planPrice = PlanPrice::query()->findOrFail($snapshot['price']['id']);

        if ($this->subscriptionGateway->providerKey() === 'pagarme') {
            return $this->executeRecurringGatewayCheckout(
                $organization,
                $buyer,
                $plan,
                $planPrice,
                $billingCycle,
                $snapshot,
                $checkoutData,
            );
        }

        return DB::transaction(function () use ($organization, $buyer, $plan, $billingCycle, $snapshot) {
            $order = BillingOrder::create([
                'organization_id' => $organization->id,
                'event_id' => null,
                'buyer_user_id' => $buyer->id,
                'mode' => BillingOrderMode::Subscription->value,
                'status' => BillingOrderStatus::Draft->value,
                'currency' => $snapshot['price']['currency'],
                'total_cents' => $snapshot['price']['amount_cents'],
                'metadata_json' => [
                    'journey' => 'subscription_checkout',
                    'plan_id' => $plan->id,
                    'plan_code' => $plan->code,
                    'billing_cycle' => $billingCycle,
                ],
            ]);

            $order->items()->create([
                'item_type' => 'subscription_plan',
                'reference_id' => $plan->id,
                'description' => "Plano {$plan->name} ({$billingCycle})",
                'quantity' => 1,
                'unit_amount_cents' => $snapshot['price']['amount_cents'],
                'total_amount_cents' => $snapshot['price']['amount_cents'],
                'snapshot_json' => $snapshot['order_item_snapshot'],
            ]);

            $gateway = $this->gatewayManager->forMode($order->mode ?? BillingOrderMode::Subscription->value);
            $checkout = $gateway->createSubscriptionCheckout($order);
            $orderStatus = ($checkout['status'] ?? null) === BillingOrderStatus::Paid->value
                ? BillingOrderStatus::Draft->value
                : ($checkout['status'] ?? BillingOrderStatus::PendingPayment->value);

            $order->forceFill([
                'gateway_provider' => $checkout['provider_key'] ?? $gateway->providerKey(),
                'gateway_order_id' => $checkout['gateway_order_id'] ?? $order->gateway_order_id,
                'status' => $orderStatus,
                'metadata_json' => array_merge($order->metadata_json ?? [], [
                    'gateway' => [
                        'provider_key' => $checkout['provider_key'] ?? $gateway->providerKey(),
                        'gateway_order_id' => $checkout['gateway_order_id'] ?? null,
                        'status' => $checkout['status'] ?? BillingOrderStatus::PendingPayment->value,
                        'checkout_url' => $checkout['checkout_url'] ?? null,
                        'confirm_url' => $checkout['confirm_url'] ?? null,
                        'expires_at' => $checkout['expires_at'] ?? null,
                        'meta' => $checkout['meta'] ?? [],
                    ],
                ]),
            ])->save();

            $result = [
                'order' => $order->fresh(['items']),
                'checkout' => $checkout,
                'subscription' => null,
                'payment' => null,
                'invoice' => null,
                'plan' => $plan,
            ];

            if (($checkout['status'] ?? null) === BillingOrderStatus::Paid->value) {
                $result = array_merge($result, $this->registerBillingGatewayPayment->execute($order, [
                    'gateway_provider' => $checkout['provider_key'] ?? $gateway->providerKey(),
                    'gateway_order_id' => $checkout['gateway_order_id'] ?? null,
                    'gateway_payment_id' => $checkout['gateway_payment_id'] ?? ($checkout['gateway_order_id'] ?? null),
                    'paid_at' => $checkout['paid_at'] ?? now(),
                    'payment_payload' => [
                        'source' => 'subscription_checkout',
                        'plan_id' => $plan->id,
                    ],
                ]));
            }

            activity()
                ->performedOn($result['order'])
                ->causedBy($buyer)
                ->withProperties([
                    'organization_id' => $organization->id,
                    'plan_id' => $plan->id,
                    'billing_cycle' => $billingCycle,
                    'provider' => $result['order']->gateway_provider,
                    'status' => $result['order']->status?->value,
                ])
                ->log('Checkout de assinatura iniciado');

            return $result;
        });
    }

    private function executeRecurringGatewayCheckout(
        Organization $organization,
        User $buyer,
        Plan $plan,
        PlanPrice $planPrice,
        string $billingCycle,
        array $snapshot,
        array $checkoutData = [],
    ): array {
        return DB::transaction(function () use ($organization, $buyer, $plan, $planPrice, $billingCycle, $snapshot, $checkoutData) {
            $order = BillingOrder::create([
                'organization_id' => $organization->id,
                'event_id' => null,
                'buyer_user_id' => $buyer->id,
                'mode' => BillingOrderMode::Subscription->value,
                'status' => BillingOrderStatus::Draft->value,
                'currency' => $snapshot['price']['currency'],
                'total_cents' => $snapshot['price']['amount_cents'],
                'payment_method' => $checkoutData['payment_method'] ?? 'credit_card',
                'customer_snapshot_json' => $checkoutData['payer'] ?? null,
                'metadata_json' => [
                    'journey' => 'subscription_checkout',
                    'plan_id' => $plan->id,
                    'plan_code' => $plan->code,
                    'plan_price_id' => $planPrice->id,
                    'billing_cycle' => $billingCycle,
                ],
            ]);

            $order->items()->create([
                'item_type' => 'subscription_plan',
                'reference_id' => $plan->id,
                'description' => "Plano {$plan->name} ({$billingCycle})",
                'quantity' => 1,
                'unit_amount_cents' => $snapshot['price']['amount_cents'],
                'total_amount_cents' => $snapshot['price']['amount_cents'],
                'snapshot_json' => $snapshot['order_item_snapshot'],
            ]);

            $planReference = $this->subscriptionGateway->ensurePlan($plan, $planPrice, [
                'journey' => 'subscription_checkout',
            ]);

            $gatewaySubscription = $this->subscriptionGateway->createSubscription($order, $plan, $planPrice, [
                'gateway_plan_id' => $planReference['gateway_plan_id'],
                'payment_method' => $checkoutData['payment_method'] ?? 'credit_card',
                'payer' => $checkoutData['payer'] ?? [],
                'credit_card' => $checkoutData['credit_card'] ?? [],
            ]);

            $order->forceFill([
                'gateway_provider' => $gatewaySubscription['provider_key'] ?? $this->subscriptionGateway->providerKey(),
                'status' => BillingOrderStatus::PendingPayment->value,
                'metadata_json' => array_merge($order->metadata_json ?? [], [
                    'gateway' => [
                        'provider_key' => $gatewaySubscription['provider_key'] ?? $this->subscriptionGateway->providerKey(),
                        'gateway_plan_id' => $gatewaySubscription['gateway_plan_id'] ?? null,
                        'gateway_subscription_id' => $gatewaySubscription['gateway_subscription_id'] ?? null,
                        'gateway_customer_id' => $gatewaySubscription['gateway_customer_id'] ?? null,
                        'gateway_card_id' => $gatewaySubscription['gateway_card_id'] ?? null,
                        'gateway_status' => $gatewaySubscription['gateway_status'] ?? null,
                        'status' => $gatewaySubscription['status'] ?? null,
                    ],
                ]),
                'gateway_response_json' => $gatewaySubscription['gateway_response'] ?? null,
            ])->save();

            $subscription = $this->upsertRecurringSubscription(
                $organization,
                $plan,
                $planPrice,
                $billingCycle,
                $gatewaySubscription,
            );

            activity()
                ->performedOn($subscription)
                ->causedBy($buyer)
                ->withProperties([
                    'organization_id' => $organization->id,
                    'plan_id' => $plan->id,
                    'plan_price_id' => $planPrice->id,
                    'billing_cycle' => $billingCycle,
                    'provider' => $this->subscriptionGateway->providerKey(),
                    'gateway_subscription_id' => $gatewaySubscription['gateway_subscription_id'] ?? null,
                ])
                ->log('Checkout recorrente da conta iniciado na Pagar.me');

            return [
                'order' => $order->fresh(['items']),
                'checkout' => [
                    'provider_key' => $this->subscriptionGateway->providerKey(),
                    'gateway_order_id' => null,
                    'status' => BillingOrderStatus::PendingPayment->value,
                    'checkout_url' => null,
                    'confirm_url' => null,
                    'expires_at' => null,
                    'meta' => [
                        'gateway_subscription_id' => $gatewaySubscription['gateway_subscription_id'] ?? null,
                        'gateway_plan_id' => $gatewaySubscription['gateway_plan_id'] ?? null,
                    ],
                ],
                'subscription' => $subscription->fresh(['plan', 'planPrice']),
                'payment' => null,
                'invoice' => null,
                'plan' => $plan,
            ];
        });
    }

    private function upsertRecurringSubscription(
        Organization $organization,
        Plan $plan,
        PlanPrice $planPrice,
        string $billingCycle,
        array $gatewaySubscription,
    ): \App\Modules\Billing\Models\Subscription {
        $startsAt = $this->asCarbon($gatewaySubscription['starts_at'] ?? null);
        $nextBillingAt = $this->asCarbon($gatewaySubscription['next_billing_at'] ?? null);
        $currentPeriodStartedAt = $this->asCarbon($gatewaySubscription['current_period_started_at'] ?? null);
        $currentPeriodEndsAt = $this->asCarbon($gatewaySubscription['current_period_ends_at'] ?? null);
        $contractStatus = (string) ($gatewaySubscription['contract_status'] ?? $gatewaySubscription['status'] ?? 'pending_activation');

        return \App\Modules\Billing\Models\Subscription::updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'plan_id' => $plan->id,
                'plan_price_id' => $planPrice->id,
                'status' => $contractStatus,
                'billing_cycle' => $billingCycle,
                'payment_method' => $gatewaySubscription['payment_method'] ?? 'credit_card',
                'starts_at' => $startsAt,
                'current_period_started_at' => $currentPeriodStartedAt,
                'current_period_ends_at' => $currentPeriodEndsAt,
                'renews_at' => $nextBillingAt,
                'next_billing_at' => $nextBillingAt,
                'ends_at' => null,
                'canceled_at' => null,
                'cancel_at_period_end' => false,
                'cancel_requested_at' => null,
                'gateway_provider' => $gatewaySubscription['provider_key'] ?? $this->subscriptionGateway->providerKey(),
                'gateway_customer_id' => $gatewaySubscription['gateway_customer_id'] ?? null,
                'gateway_plan_id' => $gatewaySubscription['gateway_plan_id'] ?? null,
                'gateway_card_id' => $gatewaySubscription['gateway_card_id'] ?? null,
                'gateway_status_reason' => $gatewaySubscription['gateway_status_reason'] ?? null,
                'billing_type' => $gatewaySubscription['billing_type'] ?? ($planPrice->billing_type ?: 'prepaid'),
                'contract_status' => $contractStatus,
                'billing_status' => $gatewaySubscription['billing_status'] ?? 'pending',
                'access_status' => $gatewaySubscription['access_status'] ?? 'provisioning',
                'gateway_subscription_id' => $gatewaySubscription['gateway_subscription_id'] ?? null,
                'metadata_json' => [
                    'gateway_status' => $gatewaySubscription['gateway_status'] ?? null,
                    'gateway_plan_id' => $gatewaySubscription['gateway_plan_id'] ?? null,
                ],
            ],
        );
    }

    private function asCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (! filled($value)) {
            return null;
        }

        return Carbon::parse((string) $value);
    }
}
