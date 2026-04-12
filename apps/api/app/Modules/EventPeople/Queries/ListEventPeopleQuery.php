<?php

namespace App\Modules\EventPeople\Queries;

use App\Modules\Events\Models\Event;
use App\Modules\EventPeople\Models\EventPerson;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ListEventPeopleQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function build(Event $event, array $filters = []): Builder
    {
        $query = EventPerson::query()
            ->forEvent($event->id)
            ->with([
                'mediaStats',
                'primaryReferencePhoto.face',
                'primaryReferencePhoto.uploadMedia',
                'referencePhotos.face',
                'referencePhotos.uploadMedia',
            ])
            ->orderByDesc('importance_rank')
            ->orderBy('display_name');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['side'])) {
            $query->where('side', $filters['side']);
        }

        if (! empty($filters['search'])) {
            $term = mb_strtolower((string) $filters['search']);
            $query->whereRaw('LOWER(display_name) LIKE ?', ["%{$term}%"]);
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
