<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\Users\Models\User;

class RefreshEventCoverageAction
{
    public function __construct(
        private readonly SyncEventCoverageTargetsAction $syncTargets,
        private readonly ProjectEventPersonGroupStatsAction $projectGroupStats,
        private readonly ProjectEventCoverageTargetStatsAction $projectTargetStats,
        private readonly ProjectEventCoverageAlertsAction $projectAlerts,
    ) {}

    public function execute(Event $event, User $user): void
    {
        $this->syncTargets->execute($event, $user);
        $this->projectGroupStats->executeForEvent($event);
        $this->projectTargetStats->executeForEvent($event);
        $this->projectAlerts->executeForEvent($event);
    }
}
