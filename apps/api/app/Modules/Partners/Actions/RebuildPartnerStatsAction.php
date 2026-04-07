<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Billing\Enums\BillingOrderMode;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Models\EventAccessGrant;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Events\Enums\EventStatus;
use App\Modules\Organizations\Enums\OrganizationType;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Partners\Models\PartnerStat;
use App\Modules\Partners\Support\PartnerProjectionTables;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RebuildPartnerStatsAction
{
    public function execute(Organization $partner): ?PartnerStat
    {
        if (($partner->type?->value ?? $partner->type) !== OrganizationType::Partner->value) {
            throw new InvalidArgumentException('Partner stats only support organizations.type = partner.');
        }

        if (! PartnerProjectionTables::hasStatsTable()) {
            return null;
        }

        $partner->loadMissing(['subscriptions.plan']);

        $subscription = $partner->subscriptions
            ->sortByDesc('id')
            ->first();

        $revenue = Invoice::query()
            ->join('billing_orders', 'billing_orders.id', '=', 'invoices.billing_order_id')
            ->where('invoices.organization_id', $partner->id)
            ->where('invoices.status', InvoiceStatus::Paid->value)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN billing_orders.mode = ? THEN invoices.amount_cents ELSE 0 END), 0) as subscription_revenue_cents,
                 COALESCE(SUM(CASE WHEN billing_orders.mode = ? THEN invoices.amount_cents ELSE 0 END), 0) as event_package_revenue_cents,
                 MAX(invoices.paid_at) as last_paid_invoice_at',
                [BillingOrderMode::Subscription->value, BillingOrderMode::EventPackage->value]
            )
            ->first();

        $subscriptionRevenue = (int) ($revenue?->subscription_revenue_cents ?? 0);
        $eventPackageRevenue = (int) ($revenue?->event_package_revenue_cents ?? 0);

        return PartnerStat::query()->updateOrCreate(
            ['organization_id' => $partner->id],
            [
                'clients_count' => $partner->clients()->count(),
                'events_count' => $partner->events()->count(),
                'active_events_count' => $partner->events()->where('status', EventStatus::Active->value)->count(),
                'team_size' => $partner->members()->where('status', 'active')->count(),
                'active_bonus_grants_count' => EventAccessGrant::query()
                    ->activeAt()
                    ->where('organization_id', $partner->id)
                    ->whereIn('source_type', ['bonus', 'manual_override'])
                    ->count(),
                'subscription_plan_code' => $subscription?->plan?->code,
                'subscription_plan_name' => $subscription?->plan?->name,
                'subscription_status' => $subscription?->status,
                'subscription_billing_cycle' => $subscription?->billing_cycle,
                'subscription_revenue_cents' => $subscriptionRevenue,
                'event_package_revenue_cents' => $eventPackageRevenue,
                'total_revenue_cents' => $subscriptionRevenue + $eventPackageRevenue,
                'last_paid_invoice_at' => $revenue?->last_paid_invoice_at,
                'refreshed_at' => now(),
            ],
        );
    }
}
