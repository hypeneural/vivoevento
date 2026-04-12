<?php

namespace App\Modules\EventPeople\Queries;

use App\Modules\EventPeople\Models\EventPersonReviewQueueItem;
use App\Modules\Events\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListEventPeopleReviewQueueQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function build(Event $event, array $filters = []): Builder
    {
        $query = EventPersonReviewQueueItem::query()
            ->where('event_id', $event->id)
            ->with(['person', 'face'])
            ->orderByDesc('priority')
            ->orderByDesc('last_signal_at')
            ->orderBy('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(Event $event, array $filters = [], int $perPage = 36): LengthAwarePaginator
    {
        return $this->build($event, $filters)->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function explain(Event $event, array $filters = []): array
    {
        $query = $this->build($event, $filters);
        $connection = $query->getConnection();
        $driver = $connection->getDriverName();
        $statement = $driver === 'sqlite' ? 'EXPLAIN QUERY PLAN ' : 'EXPLAIN ';

        return array_map(
            static fn (object $row): array => (array) $row,
            $connection->select($statement . $query->toSql(), $query->getBindings()),
        );
    }
}
