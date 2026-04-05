<?php

namespace App\Modules\Dashboard\Queries;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class TopPartnersQuery
{
    /**
     * Returns the top 4 organizations ranked by revenue, with active event counts.
     * This is only relevant for super-admin / platform-admin views.
     */
    public function execute(): array
    {
        if (Schema::hasTable('invoices') && Schema::hasTable('billing_orders')) {
            $rows = DB::select("
                SELECT *
                FROM (
                    SELECT
                        o.id,
                        COALESCE(o.trade_name, o.legal_name) AS name,
                        o.type,
                        o.logo_path,
                        (SELECT COUNT(*) FROM events WHERE organization_id = o.id AND status = 'active' AND deleted_at IS NULL) AS active_events,
                        (SELECT COUNT(*) FROM events WHERE organization_id = o.id AND status = 'active' AND commercial_mode = 'subscription_covered' AND deleted_at IS NULL) AS active_subscription_events,
                        (SELECT COUNT(*) FROM events WHERE organization_id = o.id AND status = 'active' AND commercial_mode = 'single_purchase' AND deleted_at IS NULL) AS active_paid_events,
                        (SELECT COALESCE(SUM(i.amount_cents), 0) FROM invoices i INNER JOIN billing_orders bo ON bo.id = i.billing_order_id WHERE i.organization_id = o.id AND i.status = 'paid' AND bo.mode = 'subscription') AS subscription_revenue_cents,
                        (SELECT COALESCE(SUM(i.amount_cents), 0) FROM invoices i INNER JOIN billing_orders bo ON bo.id = i.billing_order_id WHERE i.organization_id = o.id AND i.status = 'paid' AND bo.mode = 'event_package') AS event_revenue_cents
                    FROM organizations o
                    WHERE o.status = 'active'
                ) ranked_partners
                ORDER BY (ranked_partners.subscription_revenue_cents + ranked_partners.event_revenue_cents) DESC, ranked_partners.active_events DESC
                LIMIT 4
            ");
        } else {
            $rows = DB::select("
                SELECT
                    o.id,
                    COALESCE(o.trade_name, o.legal_name) AS name,
                    o.type,
                    o.logo_path,
                    (SELECT COUNT(*) FROM events WHERE organization_id = o.id AND status = 'active' AND deleted_at IS NULL) AS active_events,
                    (SELECT COUNT(*) FROM events WHERE organization_id = o.id AND status = 'active' AND commercial_mode = 'subscription_covered' AND deleted_at IS NULL) AS active_subscription_events,
                    (SELECT COUNT(*) FROM events WHERE organization_id = o.id AND status = 'active' AND commercial_mode = 'single_purchase' AND deleted_at IS NULL) AS active_paid_events,
                    0 AS subscription_revenue_cents,
                    (SELECT COALESCE(SUM(price_snapshot_cents), 0) FROM event_purchases WHERE organization_id = o.id AND status = 'paid') AS event_revenue_cents
                FROM organizations o
                WHERE o.status = 'active'
                ORDER BY event_revenue_cents DESC, active_events DESC
                LIMIT 4
            ");
        }

        return collect($rows)->map(fn ($row) => [
            'id'            => $row->id,
            'name'          => $row->name,
            'type'          => $row->type,
            'logo_url'      => $row->logo_path
                ? Storage::disk('public')->url($row->logo_path)
                : null,
            'active_events' => (int) $row->active_events,
            'active_subscription_events' => (int) ($row->active_subscription_events ?? 0),
            'active_paid_events' => (int) ($row->active_paid_events ?? 0),
            'subscription_revenue' => (int) ($row->subscription_revenue_cents ?? 0) / 100,
            'event_revenue' => (int) ($row->event_revenue_cents ?? 0) / 100,
            'revenue'       => ((int) ($row->subscription_revenue_cents ?? 0) + (int) ($row->event_revenue_cents ?? 0)) / 100,
        ])->all();
    }
}
