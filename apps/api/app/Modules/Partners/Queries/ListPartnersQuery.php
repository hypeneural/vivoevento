<?php

namespace App\Modules\Partners\Queries;

use App\Modules\Organizations\Enums\OrganizationType;
use App\Modules\Organizations\Models\Organization;
use App\Shared\Concerns\HasPortableLike;
use App\Shared\Contracts\QueryInterface;
use Illuminate\Database\Eloquent\Builder;

class ListPartnersQuery implements QueryInterface
{
    use HasPortableLike;

    public function __construct(
        protected ?string $search = null,
        protected ?string $segment = null,
        protected ?string $status = null,
        protected ?string $planCode = null,
        protected ?string $subscriptionStatus = null,
        protected ?bool $hasActiveEvents = null,
        protected ?bool $hasClients = null,
        protected ?bool $hasActiveBonusGrants = null,
        protected string $sortBy = 'created_at',
        protected string $sortDirection = 'desc',
    ) {}

    public function query(): Builder
    {
        $query = Organization::query()
            ->where('organizations.type', OrganizationType::Partner->value)
            ->leftJoin('partner_profiles', 'partner_profiles.organization_id', '=', 'organizations.id')
            ->leftJoin('partner_stats', 'partner_stats.organization_id', '=', 'organizations.id')
            ->select('organizations.*')
            ->with([
                'partnerProfile',
                'partnerStats',
                'subscription.plan',
            ]);

        if ($this->search) {
            $like = $this->likeOperator();
            $search = $this->search;

            $query->where(function (Builder $builder) use ($like, $search) {
                $builder
                    ->where('organizations.trade_name', $like, "%{$search}%")
                    ->orWhere('organizations.legal_name', $like, "%{$search}%")
                    ->orWhere('organizations.slug', $like, "%{$search}%")
                    ->orWhere('organizations.email', $like, "%{$search}%")
                    ->orWhere('organizations.phone', $like, "%{$search}%")
                    ->orWhere('organizations.document_number', $like, "%{$search}%")
                    ->orWhere('partner_profiles.segment', $like, "%{$search}%");
            });
        }

        if ($this->segment) {
            $query->where('partner_profiles.segment', $this->segment);
        }

        if ($this->status) {
            $query->where('organizations.status', $this->status);
        }

        if ($this->planCode) {
            $query->where('partner_stats.subscription_plan_code', $this->planCode);
        }

        if ($this->subscriptionStatus) {
            $query->where('partner_stats.subscription_status', $this->subscriptionStatus);
        }

        if ($this->hasActiveEvents === true) {
            $query->where('partner_stats.active_events_count', '>', 0);
        }

        if ($this->hasActiveEvents === false) {
            $query->where(function (Builder $builder) {
                $builder
                    ->whereNull('partner_stats.active_events_count')
                    ->orWhere('partner_stats.active_events_count', 0);
            });
        }

        if ($this->hasClients === true) {
            $query->where('partner_stats.clients_count', '>', 0);
        }

        if ($this->hasClients === false) {
            $query->where(function (Builder $builder) {
                $builder
                    ->whereNull('partner_stats.clients_count')
                    ->orWhere('partner_stats.clients_count', 0);
            });
        }

        if ($this->hasActiveBonusGrants === true) {
            $query->where('partner_stats.active_bonus_grants_count', '>', 0);
        }

        if ($this->hasActiveBonusGrants === false) {
            $query->where(function (Builder $builder) {
                $builder
                    ->whereNull('partner_stats.active_bonus_grants_count')
                    ->orWhere('partner_stats.active_bonus_grants_count', 0);
            });
        }

        return match ($this->sortBy) {
            'name' => $query->orderBy('organizations.trade_name', $this->sortDirection)->orderBy('organizations.id'),
            'revenue_cents' => $query->orderBy('partner_stats.total_revenue_cents', $this->sortDirection)->orderBy('organizations.trade_name'),
            'active_events_count' => $query->orderBy('partner_stats.active_events_count', $this->sortDirection)->orderBy('organizations.trade_name'),
            'clients_count' => $query->orderBy('partner_stats.clients_count', $this->sortDirection)->orderBy('organizations.trade_name'),
            'team_size' => $query->orderBy('partner_stats.team_size', $this->sortDirection)->orderBy('organizations.trade_name'),
            default => $query->orderBy('organizations.created_at', $this->sortDirection)->orderBy('organizations.trade_name'),
        };
    }
}
