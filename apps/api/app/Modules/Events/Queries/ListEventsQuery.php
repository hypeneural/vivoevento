<?php

namespace App\Modules\Events\Queries;

use App\Modules\Events\Models\Event;
use App\Shared\Contracts\QueryInterface;
use Illuminate\Database\Eloquent\Builder;

class ListEventsQuery implements QueryInterface
{
    public function __construct(
        protected ?int $organizationId = null,
        protected ?string $status = null,
        protected ?string $search = null,
    ) {}

    public function query(): Builder
    {
        $query = Event::query()->with(['organization', 'modules']);

        if ($this->organizationId) {
            $query->where('organization_id', $this->organizationId);
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->search) {
            $query->where(function (Builder $q) {
                $q->where('title', 'ilike', "%{$this->search}%")
                    ->orWhere('slug', 'ilike', "%{$this->search}%")
                    ->orWhere('location_name', 'ilike', "%{$this->search}%");
            });
        }

        return $query->latest();
    }
}
