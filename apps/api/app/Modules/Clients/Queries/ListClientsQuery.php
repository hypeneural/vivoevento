<?php

namespace App\Modules\Clients\Queries;

use App\Modules\Clients\Models\Client;
use App\Shared\Concerns\HasPortableLike;
use App\Shared\Contracts\QueryInterface;
use Illuminate\Database\Eloquent\Builder;

class ListClientsQuery implements QueryInterface
{
    use HasPortableLike;

    public function __construct(
        protected ?int $organizationId = null,
        protected ?string $search = null,
        protected ?string $type = null,
        protected ?string $planCode = null,
        protected ?bool $hasEvents = null,
        protected string $sortBy = 'created_at',
        protected string $sortDirection = 'desc',
    ) {}

    public function query(): Builder
    {
        $query = Client::query()
            ->with([
                'organization:id,uuid,type,trade_name,legal_name,slug,status',
                'organization.subscription:id,organization_id,plan_id,status,billing_cycle,starts_at,trial_ends_at,renews_at,ends_at',
                'organization.subscription.plan:id,code,name',
            ])
            ->withCount('events');

        if ($this->organizationId !== null) {
            $query->where('organization_id', $this->organizationId);
        }

        if ($this->search) {
            $search = $this->search;
            $like = $this->likeOperator();

            $query->where(function (Builder $builder) use ($search, $like) {
                $builder
                    ->where('name', $like, "%{$search}%")
                    ->orWhere('email', $like, "%{$search}%")
                    ->orWhere('phone', $like, "%{$search}%")
                    ->orWhere('document_number', $like, "%{$search}%")
                    ->orWhereHas('organization', function (Builder $organizationQuery) use ($search, $like) {
                        $organizationQuery
                            ->where('trade_name', $like, "%{$search}%")
                            ->orWhere('legal_name', $like, "%{$search}%")
                            ->orWhere('slug', $like, "%{$search}%");
                    });
            });
        }

        if ($this->type) {
            $query->where('type', $this->type);
        }

        if ($this->planCode) {
            $query->whereHas('organization.subscription.plan', function (Builder $planQuery) {
                $planQuery->where('code', $this->planCode);
            });
        }

        if ($this->hasEvents === true) {
            $query->has('events');
        }

        if ($this->hasEvents === false) {
            $query->doesntHave('events');
        }

        return match ($this->sortBy) {
            'name' => $query->orderBy('name', $this->sortDirection)->orderByDesc('created_at'),
            'events_count' => $query->orderBy('events_count', $this->sortDirection)->orderBy('name'),
            default => $query->orderBy('created_at', $this->sortDirection)->orderBy('name'),
        };
    }
}
