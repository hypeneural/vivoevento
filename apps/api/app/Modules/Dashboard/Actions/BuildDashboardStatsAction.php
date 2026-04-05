<?php

namespace App\Modules\Dashboard\Actions;

use App\Modules\Dashboard\Queries\AlertsQuery;
use App\Modules\Dashboard\Queries\EngagementByModuleQuery;
use App\Modules\Dashboard\Queries\EventsByTypeQuery;
use App\Modules\Dashboard\Queries\KpiAggregatesQuery;
use App\Modules\Dashboard\Queries\ModerationQueueQuery;
use App\Modules\Dashboard\Queries\RecentEventsQuery;
use App\Modules\Dashboard\Queries\TopPartnersQuery;
use App\Modules\Dashboard\Queries\UploadsPerHourQuery;

class BuildDashboardStatsAction
{
    /**
     * Orchestrate all dashboard queries and return the full payload.
     *
     * @param int  $organizationId  The current user's organization
     * @param bool $isAdmin         Whether the user is super-admin/platform-admin
     */
    public function execute(int $organizationId, bool $isAdmin = false): array
    {
        $kpiQuery = new KpiAggregatesQuery();

        $kpis = $kpiQuery->execute($organizationId);
        $changes = $kpiQuery->changes($organizationId);

        // Calculate change percentages
        $photosYesterday = $changes['photos_yesterday'];
        $photosTodayChangePercent = $photosYesterday > 0
            ? round(($kpis['photos_today'] - $photosYesterday) / $photosYesterday * 100, 1)
            : 0;

        // Partner count — only for admins
        if ($isAdmin) {
            $kpis['active_partners'] = \App\Modules\Organizations\Models\Organization::where('status', 'active')->count();
        } else {
            $kpis['active_partners'] = \App\Modules\Organizations\Models\OrganizationMember::where('organization_id', $organizationId)
                ->where('status', 'active')
                ->count();
        }

        return [
            'kpis' => $kpis,
            'changes' => [
                'photos_today_change'  => $photosTodayChangePercent,
                'events_new_this_week' => $changes['events_new_this_week'],
                'games_played_today'   => $changes['games_played_today'],
            ],
            'charts' => [
                'uploads_per_hour'       => (new UploadsPerHourQuery())->execute($organizationId),
                'events_by_type'         => (new EventsByTypeQuery())->execute($organizationId),
                'engagement_by_module'   => (new EngagementByModuleQuery())->execute($organizationId),
            ],
            'recent_events'   => (new RecentEventsQuery())->execute($organizationId),
            'moderation_queue' => (new ModerationQueueQuery())->execute($organizationId),
            'top_partners'    => $isAdmin ? (new TopPartnersQuery())->execute() : [],
            'alerts'          => (new AlertsQuery())->execute($organizationId),
        ];
    }
}
