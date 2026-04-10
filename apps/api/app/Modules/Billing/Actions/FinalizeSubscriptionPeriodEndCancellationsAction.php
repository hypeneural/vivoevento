<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\BillingSubscriptionGatewayInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FinalizeSubscriptionPeriodEndCancellationsAction
{
    public function __construct(
        private readonly BillingSubscriptionGatewayInterface $subscriptionGateway,
    ) {}

    public function execute(array $filters = []): array
    {
        $reference = isset($filters['reference_at'])
            ? Carbon::parse((string) $filters['reference_at'])
            : now();

        $query = Subscription::query()
            ->where('status', 'canceled')
            ->where('cancel_at_period_end', true)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $reference)
            ->orderBy('id');

        if (filled($filters['subscription_id'] ?? null)) {
            $query->where('id', (int) $filters['subscription_id']);
        }

        if (filled($filters['organization_id'] ?? null)) {
            $query->where('organization_id', (int) $filters['organization_id']);
        }

        $limit = max(1, min(200, (int) ($filters['limit'] ?? 50)));
        $subscriptions = $query->limit($limit)->get();
        $processed = [];
        $errors = [];

        foreach ($subscriptions as $subscription) {
            $lock = Cache::lock(sprintf('billing:period-end-cancel:%s', $subscription->id), 30);

            try {
                $processed[] = $lock->block(5, fn () => $this->finalizeOne($subscription->id, $reference));
            } catch (\Throwable $exception) {
                $errors[] = [
                    'subscription_id' => $subscription->id,
                    'gateway_subscription_id' => $subscription->gateway_subscription_id,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'processed' => count(array_filter($processed)),
            'failed' => count($errors),
            'results' => array_values(array_filter($processed)),
            'errors' => $errors,
            'reference_at' => $reference->toISOString(),
        ];
    }

    private function finalizeOne(int $subscriptionId, Carbon $reference): ?array
    {
        return DB::transaction(function () use ($subscriptionId, $reference) {
            /** @var Subscription|null $subscription */
            $subscription = Subscription::query()->lockForUpdate()->find($subscriptionId);

            if (
                ! $subscription
                || $subscription->status !== 'canceled'
                || ! $subscription->cancel_at_period_end
                || ! $subscription->ends_at
                || $subscription->ends_at->greaterThan($reference)
            ) {
                return null;
            }

            $gatewayCancellation = null;

            if (
                filled($subscription->gateway_subscription_id)
                && ($subscription->gateway_provider ?: $this->subscriptionGateway->providerKey()) === $this->subscriptionGateway->providerKey()
            ) {
                $gatewayCancellation = $this->subscriptionGateway->cancelSubscription($subscription, [
                    'cancel_pending_invoices' => true,
                ]);
            }

            $subscription->forceFill([
                'cancel_at_period_end' => false,
                'renews_at' => null,
                'next_billing_at' => null,
                'ends_at' => $reference,
                'access_status' => 'disabled',
                'gateway_status_reason' => $gatewayCancellation['gateway_status'] ?? $subscription->gateway_status_reason,
                'metadata_json' => array_merge($subscription->metadata_json ?? [], array_filter([
                    'period_end_cancellation_finalized_at' => $reference->toISOString(),
                    'gateway_cancellation' => $gatewayCancellation['gateway_response'] ?? null,
                ], fn (mixed $value): bool => $value !== null)),
            ])->save();

            activity()
                ->performedOn($subscription)
                ->withProperties([
                    'subscription_id' => $subscription->id,
                    'organization_id' => $subscription->organization_id,
                    'gateway_subscription_id' => $subscription->gateway_subscription_id,
                    'reference_at' => $reference->toISOString(),
                    'gateway_cancellation' => $gatewayCancellation['gateway_response'] ?? null,
                ])
                ->log('Cancelamento ao fim do ciclo finalizado');

            return [
                'subscription_id' => $subscription->id,
                'gateway_subscription_id' => $subscription->gateway_subscription_id,
                'finalized_at' => $reference->toISOString(),
            ];
        });
    }
}
