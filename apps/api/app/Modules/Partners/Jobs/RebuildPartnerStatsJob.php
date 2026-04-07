<?php

namespace App\Modules\Partners\Jobs;

use App\Modules\Organizations\Enums\OrganizationType;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Partners\Actions\RebuildPartnerStatsAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RebuildPartnerStatsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor;

    public function __construct(
        public readonly int $organizationId,
    ) {
        $this->queue = (string) config('partners.stats.queue', 'analytics');
        $this->uniqueFor = (int) config('partners.stats.unique_for', 60);
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return "partner-stats:{$this->organizationId}";
    }

    public function handle(RebuildPartnerStatsAction $rebuildPartnerStats): void
    {
        $partner = Organization::query()
            ->with(['subscriptions.plan'])
            ->find($this->organizationId);

        if (! $partner) {
            return;
        }

        if (($partner->type?->value ?? $partner->type) !== OrganizationType::Partner->value) {
            return;
        }

        $rebuildPartnerStats->execute($partner);
    }
}
