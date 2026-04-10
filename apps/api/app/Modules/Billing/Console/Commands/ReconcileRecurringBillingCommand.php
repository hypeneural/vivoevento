<?php

namespace App\Modules\Billing\Console\Commands;

use App\Modules\Billing\Actions\ReconcileRecurringSubscriptionsBatchAction;
use Illuminate\Console\Command;

class ReconcileRecurringBillingCommand extends Command
{
    protected $signature = 'billing:subscriptions:reconcile
        {--subscription-id= : Reconcile a single local subscription id}
        {--organization-id= : Limit the batch to a specific organization}
        {--gateway-subscription-id= : Reconcile a specific provider subscription id}
        {--gateway-provider= : Filter by gateway provider}
        {--contract-status=active,future,canceled : Comma-separated local contract statuses}
        {--page=1 : Provider page to inspect}
        {--size=20 : Provider page size}
        {--limit=50 : Maximum number of local subscriptions per batch}
        {--with-charge-details=1 : Load GET /charges/{charge_id} during reconcile}';

    protected $description = 'Run assisted reconcile for recurring billing subscriptions against the configured provider.';

    public function handle(ReconcileRecurringSubscriptionsBatchAction $action): int
    {
        $summary = $action->execute([
            'subscription_id' => $this->option('subscription-id'),
            'organization_id' => $this->option('organization-id'),
            'gateway_subscription_id' => $this->option('gateway-subscription-id'),
            'gateway_provider' => $this->option('gateway-provider'),
            'contract_status' => $this->option('contract-status'),
            'page' => (int) $this->option('page'),
            'size' => (int) $this->option('size'),
            'limit' => (int) $this->option('limit'),
            'with_charge_details' => (bool) $this->option('with-charge-details'),
        ]);

        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return empty($summary['errors']) ? self::SUCCESS : self::FAILURE;
    }
}
