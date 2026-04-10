<?php

namespace App\Modules\Billing\Console\Commands;

use App\Modules\Billing\Actions\FinalizeSubscriptionPeriodEndCancellationsAction;
use Illuminate\Console\Command;

class FinalizePeriodEndSubscriptionCancellationsCommand extends Command
{
    protected $signature = 'billing:subscriptions:finalize-period-end-cancellations
        {--subscription-id= : Finalize a specific local subscription id}
        {--organization-id= : Limit finalization to a specific organization}
        {--limit=50 : Maximum number of subscriptions to finalize in one run}
        {--reference-at= : Override the boundary timestamp used by the scheduler}';

    protected $description = 'Finalize local cancel_at_period_end subscriptions and sync DELETE /subscriptions/{id} on the boundary.';

    public function handle(FinalizeSubscriptionPeriodEndCancellationsAction $action): int
    {
        $summary = $action->execute([
            'subscription_id' => $this->option('subscription-id'),
            'organization_id' => $this->option('organization-id'),
            'limit' => (int) $this->option('limit'),
            'reference_at' => $this->option('reference-at'),
        ]);

        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return empty($summary['errors']) ? self::SUCCESS : self::FAILURE;
    }
}
