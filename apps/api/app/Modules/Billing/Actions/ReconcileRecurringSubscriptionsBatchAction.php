<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\Subscription;

class ReconcileRecurringSubscriptionsBatchAction
{
    public function __construct(
        private readonly ReconcileRecurringSubscriptionAction $reconcileRecurringSubscription,
    ) {}

    public function execute(array $filters = []): array
    {
        $query = Subscription::query()
            ->whereNotNull('gateway_subscription_id')
            ->orderBy('id');

        if (filled($filters['subscription_id'] ?? null)) {
            $query->where('id', (int) $filters['subscription_id']);
        }

        if (filled($filters['organization_id'] ?? null)) {
            $query->where('organization_id', (int) $filters['organization_id']);
        }

        if (filled($filters['gateway_subscription_id'] ?? null)) {
            $query->where('gateway_subscription_id', (string) $filters['gateway_subscription_id']);
        }

        if (filled($filters['gateway_provider'] ?? null)) {
            $query->where('gateway_provider', (string) $filters['gateway_provider']);
        }

        if (filled($filters['contract_status'] ?? null)) {
            $statuses = array_values(array_filter(array_map(
                static fn (mixed $status): string => trim((string) $status),
                explode(',', (string) $filters['contract_status'])
            )));

            if ($statuses !== []) {
                $query->whereIn('contract_status', $statuses);
            }
        }

        $limit = max(1, min(200, (int) ($filters['limit'] ?? 50)));
        $subscriptions = $query->limit($limit)->get();
        $results = [];
        $errors = [];

        foreach ($subscriptions as $subscription) {
            try {
                $results[] = $this->reconcileRecurringSubscription->execute($subscription, $filters);
            } catch (\Throwable $exception) {
                $errors[] = [
                    'subscription_id' => $subscription->id,
                    'gateway_subscription_id' => $subscription->gateway_subscription_id,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'processed' => count($results),
            'failed' => count($errors),
            'results' => $results,
            'errors' => $errors,
        ];
    }
}
