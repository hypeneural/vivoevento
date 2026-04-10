<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\BillingSubscriptionGatewayInterface;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CancelCurrentSubscriptionAction
{
    public function __construct(
        private readonly BillingSubscriptionGatewayInterface $subscriptionGateway,
    ) {}

    public function execute(
        Organization $organization,
        ?User $actor = null,
        string $effective = 'period_end',
        ?string $reason = null,
    ): Subscription {
        Log::info('billing.recurring.subscription.cancel_requested', [
            'organization_id' => $organization->id,
            'actor_user_id' => $actor?->id,
            'effective' => $effective,
            'reason' => $reason,
        ]);

        return DB::transaction(function () use ($organization, $actor, $effective, $reason) {
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

            if (in_array($subscription->status, ['canceled', 'expired'], true)) {
                if ($subscription->status === 'canceled' && $effective === 'immediately' && $subscription->isCanceledPendingEnd()) {
                    $gatewayCancellation = null;

                    if (
                        $subscription->gateway_provider === $this->subscriptionGateway->providerKey()
                        && filled($subscription->gateway_subscription_id)
                    ) {
                        $gatewayCancellation = $this->subscriptionGateway->cancelSubscription($subscription, [
                            'cancel_pending_invoices' => true,
                        ]);
                    }

                    $subscription->forceFill([
                        'ends_at' => now(),
                        'renews_at' => null,
                        'next_billing_at' => null,
                        'cancel_at_period_end' => false,
                        'access_status' => 'disabled',
                        'gateway_status_reason' => $gatewayCancellation['gateway_status'] ?? $subscription->gateway_status_reason,
                    ])->save();
                }

                return $subscription->fresh(['plan.features']);
            }

            $canceledAt = now();
            $gatewayCancellation = null;

            if (
                $effective === 'immediately'
                && $subscription->gateway_provider === $this->subscriptionGateway->providerKey()
                && filled($subscription->gateway_subscription_id)
            ) {
                $gatewayCancellation = $this->subscriptionGateway->cancelSubscription($subscription, [
                    'cancel_pending_invoices' => true,
                ]);
            }

            $endsAt = $effective === 'immediately'
                ? $canceledAt->copy()
                : $this->resolvePeriodEnd($subscription, $canceledAt);

            $subscription->forceFill([
                'status' => 'canceled',
                'canceled_at' => $subscription->canceled_at ?? $canceledAt,
                'cancel_requested_at' => $subscription->cancel_requested_at ?? $canceledAt,
                'cancel_at_period_end' => $effective !== 'immediately',
                'ends_at' => $endsAt,
                'renews_at' => $effective === 'immediately' ? null : $endsAt,
                'next_billing_at' => $effective === 'immediately' ? null : $subscription->next_billing_at,
                'gateway_status_reason' => $effective === 'immediately'
                    ? (string) ($gatewayCancellation['gateway_status'] ?? 'canceled')
                    : $subscription->gateway_status_reason,
                'contract_status' => 'canceled',
                'access_status' => $effective === 'immediately' ? 'disabled' : ($subscription->access_status ?? 'enabled'),
            ])->save();

            activity()
                ->performedOn($subscription)
                ->causedBy($actor)
                ->withProperties([
                    'organization_id' => $organization->id,
                    'reason' => $reason,
                    'effective' => $effective,
                    'previous_status' => $subscription->getOriginal('status'),
                    'effective_status' => 'canceled',
                    'ends_at' => $subscription->ends_at?->toISOString(),
                    'gateway_subscription_id' => $subscription->gateway_subscription_id,
                    'gateway_provider' => $subscription->gateway_provider,
                    'gateway_cancellation' => $gatewayCancellation['gateway_response'] ?? null,
                ])
                ->log('Assinatura da conta cancelada');

            Log::info('billing.recurring.subscription.canceled', [
                'organization_id' => $organization->id,
                'subscription_id' => $subscription->id,
                'gateway_subscription_id' => $subscription->gateway_subscription_id,
                'effective' => $effective,
                'status' => $subscription->status,
                'contract_status' => $subscription->contract_status,
                'access_status' => $subscription->access_status,
                'ends_at' => $subscription->ends_at?->toISOString(),
            ]);

            return $subscription->fresh(['plan.features']);
        });
    }

    private function resolvePeriodEnd(Subscription $subscription, Carbon $reference): Carbon
    {
        $candidates = collect([
            $subscription->renews_at,
            $subscription->trial_ends_at,
            $subscription->ends_at,
        ])->filter(fn ($candidate) => $candidate instanceof Carbon && $candidate->greaterThan($reference));

        return ($candidates->sortBy(fn (Carbon $candidate) => $candidate->getTimestamp())->first() ?? $reference)->copy();
    }
}
