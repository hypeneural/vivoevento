<?php

namespace App\Modules\Events\Queries;

use App\Modules\Events\Models\Event;
use App\Shared\Concerns\HasPortableLike;
use App\Shared\Contracts\QueryInterface;
use Illuminate\Database\Eloquent\Builder;

class ListEventsQuery implements QueryInterface
{
    use HasPortableLike;
    public function __construct(
        protected ?int $organizationId = null,
        protected ?array $eventIds = null,
        protected ?int $clientId = null,
        protected ?string $status = null,
        protected ?string $eventType = null,
        protected ?string $commercialMode = null,
        protected ?string $module = null,
        protected ?string $search = null,
        protected ?string $dateFrom = null,
        protected ?string $dateTo = null,
        protected string $sortBy = 'starts_at',
        protected string $sortDirection = 'desc',
    ) {}

    public function query(): Builder
    {
        $query = Event::query()
            ->with([
                'organization:id,trade_name,legal_name,slug',
                'client:id,name',
                'modules:event_id,module_key,is_enabled',
                'wallSettings:id,event_id,wall_code,is_enabled,status',
            ])
            ->withCount('media');

        if ($this->organizationId) {
            $query->where('organization_id', $this->organizationId);
        }

        if (is_array($this->eventIds)) {
            $normalizedEventIds = collect($this->eventIds)
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();

            if ($normalizedEventIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id', $normalizedEventIds);
            }
        }

        if ($this->clientId) {
            $query->where('client_id', $this->clientId);
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->eventType) {
            $query->where('event_type', $this->eventType);
        }

        if ($this->commercialMode) {
            $query->where('commercial_mode', $this->commercialMode);
        }

        if ($this->module) {
            $query->whereHas('modules', function (Builder $moduleQuery) {
                $moduleQuery
                    ->where('module_key', $this->module)
                    ->where('is_enabled', true);
            });
        }

        if ($this->search) {
            $like = $this->likeOperator();

            $query->where(function (Builder $q) use ($like) {
                $q->where('title', $like, "%{$this->search}%")
                    ->orWhere('slug', $like, "%{$this->search}%")
                    ->orWhere('location_name', $like, "%{$this->search}%")
                    ->orWhereHas('client', function (Builder $clientQuery) use ($like) {
                        $clientQuery->where('name', $like, "%{$this->search}%");
                    })
                    ->orWhereHas('organization', function (Builder $organizationQuery) use ($like) {
                        $organizationQuery
                            ->where('trade_name', $like, "%{$this->search}%")
                            ->orWhere('legal_name', $like, "%{$this->search}%")
                            ->orWhere('slug', $like, "%{$this->search}%");
                    });
            });
        }

        if ($this->dateFrom && $this->dateTo) {
            $query->where(function (Builder $dateQuery) {
                $dateQuery
                    ->whereDate('starts_at', '<=', $this->dateTo)
                    ->where(function (Builder $overlapQuery) {
                        $overlapQuery
                            ->whereNull('ends_at')
                            ->orWhereDate('ends_at', '>=', $this->dateFrom);
                    });
            });
        } elseif ($this->dateFrom) {
            $query->where(function (Builder $dateQuery) {
                $dateQuery
                    ->whereDate('starts_at', '>=', $this->dateFrom)
                    ->orWhereDate('ends_at', '>=', $this->dateFrom);
            });
        } elseif ($this->dateTo) {
            $query->whereDate('starts_at', '<=', $this->dateTo);
        }

        return match ($this->sortBy) {
            'title' => $query->orderBy('title', $this->sortDirection),
            'status' => $query->orderBy('status', $this->sortDirection)->orderByDesc('starts_at'),
            'created_at' => $query->orderBy('created_at', $this->sortDirection),
            default => $query
                ->orderByRaw('starts_at IS NULL')
                ->orderBy('starts_at', $this->sortDirection)
                ->orderByDesc('created_at'),
        };
    }
}
