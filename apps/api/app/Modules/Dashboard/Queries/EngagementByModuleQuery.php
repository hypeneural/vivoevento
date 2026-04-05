<?php

namespace App\Modules\Dashboard\Queries;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EngagementByModuleQuery
{
    private const DEFAULT_MODULES = [
        ['module' => 'Live', 'interactions' => 0, 'percentage' => 0],
        ['module' => 'Wall', 'interactions' => 0, 'percentage' => 0],
        ['module' => 'Play', 'interactions' => 0, 'percentage' => 0],
        ['module' => 'Hub',  'interactions' => 0, 'percentage' => 0],
    ];

    /**
     * Counts analytics_events grouped by module for the last 30 days.
     */
    public function execute(int $organizationId): array
    {
        if (! Schema::hasTable('analytics_events')) {
            return self::DEFAULT_MODULES;
        }

        $thirtyDaysAgo = Carbon::now()->subDays(30)->toDateTimeString();

        // Use a subquery to avoid HAVING on aliased column (works across PG + SQLite)
        $rows = DB::select("
            SELECT module, SUM(cnt) AS interactions FROM (
                SELECT
                    CASE
                        WHEN event_name LIKE 'media.%' OR event_name LIKE 'upload.%' THEN 'Live'
                        WHEN event_name LIKE 'wall.%' THEN 'Wall'
                        WHEN event_name LIKE 'play.%' OR event_name LIKE 'game.%' THEN 'Play'
                        WHEN event_name LIKE 'hub.%' THEN 'Hub'
                        ELSE NULL
                    END AS module,
                    1 AS cnt
                FROM analytics_events
                WHERE organization_id = ?
                  AND occurred_at >= ?
            ) AS sub
            WHERE module IS NOT NULL
            GROUP BY module
            ORDER BY interactions DESC
        ", [$organizationId, $thirtyDaysAgo]);

        if (empty($rows)) {
            return self::DEFAULT_MODULES;
        }

        $maxInteractions = max(array_map(fn ($r) => (int) $r->interactions, $rows));

        return collect($rows)->map(fn ($row) => [
            'module'       => $row->module,
            'interactions' => (int) $row->interactions,
            'percentage'   => $maxInteractions > 0
                ? round($row->interactions / $maxInteractions * 100, 1)
                : 0,
        ])->all();
    }
}
