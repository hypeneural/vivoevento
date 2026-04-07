<?php

namespace App\Modules\Dashboard\Queries;

use App\Modules\MediaProcessing\Enums\MediaProcessingStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KpiAggregatesQuery
{
    /**
     * Returns all KPI values in a single round-trip using sub-selects.
     * Gracefully handles missing tables.
     */
    public function execute(int $organizationId): array
    {
        $today = Carbon::today()->toDateTimeString();
        $monthStart = Carbon::now()->startOfMonth()->toDateTimeString();
        $thirtyDaysAgo = Carbon::now()->subDays(30)->toDateTimeString();

        $orgEventsSubquery = "(SELECT id FROM events WHERE organization_id = {$organizationId} AND deleted_at IS NULL)";

        // Build the SELECT dynamically based on which tables exist
        $selects = [
            "(SELECT COUNT(*) FROM events WHERE organization_id = ? AND status = 'active' AND deleted_at IS NULL) AS active_events",
            "(SELECT COUNT(*) FROM events WHERE organization_id = ? AND status = 'active' AND commercial_mode = 'subscription_covered' AND deleted_at IS NULL) AS active_events_subscription_covered",
            "(SELECT COUNT(*) FROM events WHERE organization_id = ? AND status = 'active' AND commercial_mode = 'single_purchase' AND deleted_at IS NULL) AS active_events_single_purchase",
            "(SELECT COUNT(*) FROM events WHERE organization_id = ? AND status = 'active' AND commercial_mode = 'trial' AND deleted_at IS NULL) AS active_events_trial",
            "(SELECT COUNT(*) FROM events WHERE organization_id = ? AND status = 'active' AND commercial_mode IN ('bonus', 'manual_override') AND deleted_at IS NULL) AS active_events_bonus",
        ];
        $bindings = [
            $organizationId,
            $organizationId,
            $organizationId,
            $organizationId,
            $organizationId,
        ];

        // event_media
        if (Schema::hasTable('event_media')) {
            $selects[] = "(SELECT COUNT(*) FROM event_media WHERE event_id IN {$orgEventsSubquery} AND created_at >= ?) AS photos_today";
            $bindings[] = $today;

            $selects[] = "(SELECT COUNT(*) FROM event_media WHERE event_id IN {$orgEventsSubquery} AND moderation_status = 'approved' AND created_at >= ?) AS photos_approved_today";
            $bindings[] = $today;

            $selects[] = "(SELECT COUNT(*) FROM event_media WHERE event_id IN {$orgEventsSubquery} AND moderation_status = 'pending') AS pending_moderation";

            $selects[] = "(SELECT COUNT(*) FROM event_media WHERE event_id IN {$orgEventsSubquery} AND processing_status = ?) AS processing_errors";
            $bindings[] = MediaProcessingStatus::Failed->value;
        }

        // play_game_sessions
        if (Schema::hasTable('play_game_sessions') && Schema::hasTable('play_event_games')) {
            $selects[] = "(SELECT COUNT(*) FROM play_game_sessions WHERE event_game_id IN (SELECT id FROM play_event_games WHERE event_id IN {$orgEventsSubquery}) AND created_at >= ?) AS games_played";
            $bindings[] = $thirtyDaysAgo;
        }

        // analytics_events
        if (Schema::hasTable('analytics_events')) {
            $selects[] = "(SELECT COUNT(*) FROM analytics_events WHERE organization_id = ? AND event_name = 'hub.page_view' AND occurred_at >= ?) AS hub_accesses";
            $bindings[] = $organizationId;
            $bindings[] = $thirtyDaysAgo;
        }

        if (Schema::hasTable('invoices') && Schema::hasTable('billing_orders')) {
            $selects[] = "(SELECT COALESCE(SUM(i.amount_cents), 0) FROM invoices i WHERE i.organization_id = ? AND i.status = 'paid' AND COALESCE(i.paid_at, i.issued_at, i.created_at) >= ?) AS revenue_cents";
            $bindings[] = $organizationId;
            $bindings[] = $monthStart;

            $selects[] = "(SELECT COALESCE(SUM(i.amount_cents), 0) FROM invoices i INNER JOIN billing_orders bo ON bo.id = i.billing_order_id WHERE i.organization_id = ? AND i.status = 'paid' AND bo.mode = 'subscription' AND COALESCE(i.paid_at, i.issued_at, i.created_at) >= ?) AS subscription_revenue_cents";
            $bindings[] = $organizationId;
            $bindings[] = $monthStart;

            $selects[] = "(SELECT COALESCE(SUM(i.amount_cents), 0) FROM invoices i INNER JOIN billing_orders bo ON bo.id = i.billing_order_id WHERE i.organization_id = ? AND i.status = 'paid' AND bo.mode = 'event_package' AND COALESCE(i.paid_at, i.issued_at, i.created_at) >= ?) AS event_revenue_cents";
            $bindings[] = $organizationId;
            $bindings[] = $monthStart;
        } elseif (Schema::hasTable('event_purchases')) {
            $selects[] = "(SELECT COALESCE(SUM(price_snapshot_cents), 0) FROM event_purchases WHERE organization_id = ? AND status = 'paid' AND purchased_at >= ?) AS revenue_cents";
            $bindings[] = $organizationId;
            $bindings[] = $monthStart;

            $selects[] = "0 AS subscription_revenue_cents";
            $selects[] = "(SELECT COALESCE(SUM(price_snapshot_cents), 0) FROM event_purchases WHERE organization_id = ? AND status = 'paid' AND purchased_at >= ?) AS event_revenue_cents";
            $bindings[] = $organizationId;
            $bindings[] = $monthStart;
        }

        $sql = 'SELECT ' . implode(",\n", $selects);
        $result = DB::selectOne($sql, $bindings);

        $photosToday = (int) ($result->photos_today ?? 0);
        $approvedToday = (int) ($result->photos_approved_today ?? 0);

        return [
            'active_events'         => (int) ($result->active_events ?? 0),
            'active_events_subscription_covered' => (int) ($result->active_events_subscription_covered ?? 0),
            'active_events_single_purchase' => (int) ($result->active_events_single_purchase ?? 0),
            'active_events_trial' => (int) ($result->active_events_trial ?? 0),
            'active_events_bonus' => (int) ($result->active_events_bonus ?? 0),
            'photos_today'          => $photosToday,
            'photos_approved_today' => $approvedToday,
            'moderation_rate'       => $photosToday > 0 ? round($approvedToday / $photosToday * 100, 1) : 0,
            'games_played'          => (int) ($result->games_played ?? 0),
            'hub_accesses'          => (int) ($result->hub_accesses ?? 0),
            'revenue_cents'         => (int) ($result->revenue_cents ?? 0),
            'subscription_revenue_cents' => (int) ($result->subscription_revenue_cents ?? 0),
            'event_revenue_cents' => (int) ($result->event_revenue_cents ?? 0),
            'pending_moderation'    => (int) ($result->pending_moderation ?? 0),
            'processing_errors'     => (int) ($result->processing_errors ?? 0),
        ];
    }

    /**
     * Compute percentage changes vs yesterday / last week.
     */
    public function changes(int $organizationId): array
    {
        $yesterday = Carbon::yesterday()->toDateTimeString();
        $yesterdayEnd = Carbon::yesterday()->endOfDay()->toDateTimeString();
        $weekAgo = Carbon::now()->subWeek()->toDateTimeString();

        $orgEventsSubquery = "(SELECT id FROM events WHERE organization_id = {$organizationId} AND deleted_at IS NULL)";

        $selects = [];
        $bindings = [];

        if (Schema::hasTable('event_media')) {
            $selects[] = "(SELECT COUNT(*) FROM event_media WHERE event_id IN {$orgEventsSubquery} AND created_at >= ? AND created_at <= ?) AS photos_yesterday";
            $bindings[] = $yesterday;
            $bindings[] = $yesterdayEnd;
        }

        $selects[] = "(SELECT COUNT(*) FROM events WHERE organization_id = ? AND status = 'active' AND deleted_at IS NULL AND created_at >= ?) AS events_new_this_week";
        $bindings[] = $organizationId;
        $bindings[] = $weekAgo;

        if (Schema::hasTable('play_game_sessions') && Schema::hasTable('play_event_games')) {
            $selects[] = "(SELECT COUNT(*) FROM play_game_sessions WHERE event_game_id IN (SELECT id FROM play_event_games WHERE event_id IN {$orgEventsSubquery}) AND created_at >= CURRENT_DATE) AS games_played_today";
        }

        if (empty($selects)) {
            return ['photos_yesterday' => 0, 'events_new_this_week' => 0, 'games_played_today' => 0];
        }

        $sql = 'SELECT ' . implode(",\n", $selects);
        $result = DB::selectOne($sql, $bindings);

        return [
            'photos_yesterday'     => (int) ($result->photos_yesterday ?? 0),
            'events_new_this_week' => (int) ($result->events_new_this_week ?? 0),
            'games_played_today'   => (int) ($result->games_played_today ?? 0),
        ];
    }
}
