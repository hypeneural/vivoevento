<?php

namespace App\Modules\Dashboard\Queries;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UploadsPerHourQuery
{
    /**
     * Returns uploads grouped by hour for the current day.
     * Always returns 24 entries (00:00 – 23:00), filling gaps with 0.
     */
    public function execute(int $organizationId): array
    {
        if (! Schema::hasTable('event_media')) {
            return $this->emptyHours();
        }

        $today = Carbon::today()->toDateTimeString();
        $orgEventsSubquery = "(SELECT id FROM events WHERE organization_id = {$organizationId} AND deleted_at IS NULL)";

        $driver = DB::connection()->getDriverName();

        $hourExpr = $driver === 'sqlite'
            ? "CAST(strftime('%H', created_at) AS INTEGER)"
            : "EXTRACT(HOUR FROM created_at)::int";

        $rows = DB::select("
            SELECT {$hourExpr} AS hour, COUNT(*) AS uploads
            FROM event_media
            WHERE event_id IN {$orgEventsSubquery}
              AND created_at >= ?
            GROUP BY hour
            ORDER BY hour
        ", [$today]);

        $byHour = collect($rows)->keyBy('hour');

        return collect(range(0, 23))->map(fn (int $h) => [
            'hour'    => str_pad($h, 2, '0', STR_PAD_LEFT) . ':00',
            'uploads' => (int) ($byHour->get($h)?->uploads ?? 0),
        ])->all();
    }

    private function emptyHours(): array
    {
        return collect(range(0, 23))->map(fn (int $h) => [
            'hour'    => str_pad($h, 2, '0', STR_PAD_LEFT) . ':00',
            'uploads' => 0,
        ])->all();
    }
}
