<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\BillingOrderMode;
use App\Modules\Billing\Enums\BillingOrderStatus;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Services\BillingGatewayManager;
use App\Modules\Billing\Services\PlanSnapshotService;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Plans\Models\Plan;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;

class CreateSubscriptionCheckoutAction
{
    public function __construct(
        private readonly PlanSnapshotService $planSnapshots,
        private readonly BillingGatewayManager $gatewayManager,
        private readonly RegisterBillingGatewayPaymentAction $registerBillingGatewayPayment,
    ) {}

    public function execute(
        Organization $organization,
        User $buyer,
        Plan $plan,
        string $billingCycle = 'monthly',
    ): array {
        $snapshot = $this->planSnapshots->build($plan, $billingCycle);

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
}
