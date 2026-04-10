<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\BillingOrderMode;
use App\Modules\Billing\Enums\BillingOrderStatus;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\BillingGatewayManager;
use App\Modules\Billing\Services\BillingSubscriptionGatewayInterface;
use App\Modules\Billing\Services\PlanSnapshotService;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Plans\Models\Plan;
use App\Modules\Plans\Models\PlanPrice;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
            $currentSubscription = Subscription::query()
                ->where('organization_id', $organization->id)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $this->guardDuplicateRecurringCheckout($currentSubscription, $plan, $planPrice, $billingCycle, $organization, $buyer);

            $previousSubscriptionSnapshot = $this->snapshotSubscription($currentSubscription);

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
                    'previous_subscription' => $previousSubscriptionSnapshot,
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

            Log::info('billing.recurring.checkout.started', [
                'organization_id' => $organization->id,
                'buyer_user_id' => $buyer->id,
                'plan_id' => $plan->id,
                'plan_price_id' => $planPrice->id,
                'billing_cycle' => $billingCycle,
                'previous_subscription_id' => $currentSubscription?->id,
                'previous_gateway_subscription_id' => $currentSubscription?->gateway_subscription_id,
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

            $previousCancellation = $this->cancelPreviousGatewaySubscriptionIfNeeded(
                $currentSubscription,
                $gatewaySubscription,
                $organization,
                $buyer,
                $order,
            );

            $subscription = $this->upsertRecurringSubscription(
                $organization,
                $plan,
                $planPrice,
                $billingCycle,
                $gatewaySubscription,
                $currentSubscription,
                $previousCancellation,
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

            Log::info('billing.recurring.checkout.completed', [
                'organization_id' => $organization->id,
                'buyer_user_id' => $buyer->id,
                'order_id' => $order->id,
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'plan_price_id' => $planPrice->id,
                'billing_cycle' => $billingCycle,
                'gateway_plan_id' => $gatewaySubscription['gateway_plan_id'] ?? null,
                'gateway_subscription_id' => $gatewaySubscription['gateway_subscription_id'] ?? null,
                'replaced_previous_subscription' => $previousCancellation['replaced'] ?? false,
                'previous_gateway_subscription_id' => $previousCancellation['previous_gateway_subscription_id'] ?? null,
                'previous_cancellation_status' => $previousCancellation['cancel_status'] ?? null,
            ]);

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
        ?Subscription $previousSubscription = null,
        ?array $previousCancellation = null,
    ): \App\Modules\Billing\Models\Subscription {
        $startsAt = $this->asCarbon($gatewaySubscription['starts_at'] ?? null);
        $nextBillingAt = $this->asCarbon($gatewaySubscription['next_billing_at'] ?? null);
        $currentPeriodStartedAt = $this->asCarbon($gatewaySubscription['current_period_started_at'] ?? null);
        $currentPeriodEndsAt = $this->asCarbon($gatewaySubscription['current_period_ends_at'] ?? null);
        $contractStatus = (string) ($gatewaySubscription['contract_status'] ?? $gatewaySubscription['status'] ?? 'pending_activation');
        $metadata = array_filter([
            'gateway_status' => $gatewaySubscription['gateway_status'] ?? null,
            'gateway_plan_id' => $gatewaySubscription['gateway_plan_id'] ?? null,
            'replaced_subscription' => $previousSubscription ? array_filter([
                'id' => $previousSubscription->id,
                'plan_id' => $previousSubscription->plan_id,
                'plan_price_id' => $previousSubscription->plan_price_id,
                'billing_cycle' => $previousSubscription->billing_cycle,
                'gateway_subscription_id' => $previousSubscription->gateway_subscription_id,
            ], fn (mixed $value): bool => $value !== null && $value !== '') : null,
            'previous_cancellation' => $previousCancellation ? array_filter([
                'replaced' => $previousCancellation['replaced'] ?? null,
                'cancel_status' => $previousCancellation['cancel_status'] ?? null,
                'previous_gateway_subscription_id' => $previousCancellation['previous_gateway_subscription_id'] ?? null,
                'cancel_error' => $previousCancellation['cancel_error'] ?? null,
            ], fn (mixed $value): bool => $value !== null && $value !== '') : null,
        ], fn (mixed $value): bool => $value !== null && $value !== []);

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
                'metadata_json' => $metadata,
            ],
        );
    }

    private function guardDuplicateRecurringCheckout(
        ?Subscription $currentSubscription,
        Plan $plan,
        PlanPrice $planPrice,
        string $billingCycle,
        Organization $organization,
        User $buyer,
    ): void {
        if (! $currentSubscription) {
            return;
        }

        $isOpenSubscription = ! in_array($currentSubscription->status, ['canceled', 'expired'], true);
        $isSameSelection = (int) $currentSubscription->plan_id === (int) $plan->id
            && (int) ($currentSubscription->plan_price_id ?? 0) === (int) $planPrice->id
            && (string) $currentSubscription->billing_cycle === $billingCycle;

        if (! $isOpenSubscription || ! $isSameSelection || $currentSubscription->cancel_at_period_end) {
            return;
        }

        Log::warning('billing.recurring.checkout.duplicate_selection_blocked', [
            'organization_id' => $organization->id,
            'buyer_user_id' => $buyer->id,
            'subscription_id' => $currentSubscription->id,
            'plan_id' => $plan->id,
            'plan_price_id' => $planPrice->id,
            'billing_cycle' => $billingCycle,
        ]);

        throw ValidationException::withMessages([
            'plan_id' => ['A conta ja esta nesse plano e ciclo de cobranca.'],
        ]);
    }

    private function cancelPreviousGatewaySubscriptionIfNeeded(
        ?Subscription $currentSubscription,
        array $gatewaySubscription,
        Organization $organization,
        User $buyer,
        BillingOrder $order,
    ): array {
        if (! $currentSubscription) {
            return ['replaced' => false];
        }

        $previousGatewaySubscriptionId = filled($currentSubscription->gateway_subscription_id)
            ? (string) $currentSubscription->gateway_subscription_id
            : null;
        $newGatewaySubscriptionId = filled($gatewaySubscription['gateway_subscription_id'] ?? null)
            ? (string) $gatewaySubscription['gateway_subscription_id']
            : null;

        if (
            ! $previousGatewaySubscriptionId
            || $previousGatewaySubscriptionId === $newGatewaySubscriptionId
            || in_array($currentSubscription->status, ['canceled', 'expired'], true)
        ) {
            return [
                'replaced' => false,
                'previous_gateway_subscription_id' => $previousGatewaySubscriptionId,
            ];
        }

        $result = [
            'replaced' => true,
            'previous_subscription_id' => $currentSubscription->id,
            'previous_gateway_subscription_id' => $previousGatewaySubscriptionId,
            'cancel_status' => null,
            'cancel_error' => null,
        ];

        if (
            $currentSubscription->gateway_provider !== $this->subscriptionGateway->providerKey()
            || ! filled($currentSubscription->gateway_subscription_id)
        ) {
            Log::info('billing.recurring.checkout.replaced_previous_local_subscription', [
                'organization_id' => $organization->id,
                'buyer_user_id' => $buyer->id,
                'order_id' => $order->id,
                'previous_subscription_id' => $currentSubscription->id,
                'previous_gateway_provider' => $currentSubscription->gateway_provider,
                'previous_gateway_subscription_id' => $previousGatewaySubscriptionId,
            ]);

            return $result;
        }

        try {
            $cancellation = $this->subscriptionGateway->cancelSubscription($currentSubscription, [
                'cancel_pending_invoices' => true,
            ]);

            $result['cancel_status'] = $cancellation['gateway_status'] ?? 'canceled';
            $orderMetadata = is_array($order->metadata_json) ? $order->metadata_json : [];
            $previousMetadata = is_array($orderMetadata['previous_subscription'] ?? null)
                ? $orderMetadata['previous_subscription']
                : [];

            $order->forceFill([
                'metadata_json' => array_merge($orderMetadata, [
                    'previous_subscription' => array_merge(
                        $previousMetadata,
                        [
                            'cancel_status' => $result['cancel_status'],
                            'canceled_remotely_at' => now()->toISOString(),
                        ],
                    ),
                ]),
            ])->save();

            Log::info('billing.recurring.checkout.replaced_previous_subscription', [
                'organization_id' => $organization->id,
                'buyer_user_id' => $buyer->id,
                'order_id' => $order->id,
                'previous_subscription_id' => $currentSubscription->id,
                'previous_gateway_subscription_id' => $previousGatewaySubscriptionId,
                'new_gateway_subscription_id' => $newGatewaySubscriptionId,
                'cancel_status' => $result['cancel_status'],
            ]);
        } catch (\Throwable $exception) {
            $result['cancel_error'] = $exception->getMessage();
            $orderMetadata = is_array($order->metadata_json) ? $order->metadata_json : [];
            $previousMetadata = is_array($orderMetadata['previous_subscription'] ?? null)
                ? $orderMetadata['previous_subscription']
                : [];

            $order->forceFill([
                'metadata_json' => array_merge($orderMetadata, [
                    'previous_subscription' => array_merge(
                        $previousMetadata,
                        [
                            'cancel_error' => $exception->getMessage(),
                            'cancel_failed_at' => now()->toISOString(),
                        ],
                    ),
                ]),
            ])->save();

            Log::error('billing.recurring.checkout.previous_cancel_failed', [
                'organization_id' => $organization->id,
                'buyer_user_id' => $buyer->id,
                'order_id' => $order->id,
                'previous_subscription_id' => $currentSubscription->id,
                'previous_gateway_subscription_id' => $previousGatewaySubscriptionId,
                'new_gateway_subscription_id' => $newGatewaySubscriptionId,
                'error' => $exception->getMessage(),
            ]);
        }

        return $result;
    }

    private function snapshotSubscription(?Subscription $subscription): ?array
    {
        if (! $subscription) {
            return null;
        }

        return array_filter([
            'id' => $subscription->id,
            'plan_id' => $subscription->plan_id,
            'plan_price_id' => $subscription->plan_price_id,
            'billing_cycle' => $subscription->billing_cycle,
            'status' => $subscription->status,
            'gateway_provider' => $subscription->gateway_provider,
            'gateway_subscription_id' => $subscription->gateway_subscription_id,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
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
