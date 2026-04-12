<?php

namespace App\Modules\EventPeople\Queries;

use App\Modules\EventPeople\Models\EventRelationalCollection;
use App\Modules\Events\Models\Event;
use Illuminate\Database\Eloquent\Collection;

class ListEventRelationalCollectionsQuery
{
    /**
     * @return Collection<int, EventRelationalCollection>
     */
    public function get(Event $event): Collection
    {
        return EventRelationalCollection::query()
            ->forEvent($event->id)
            ->with([
                'personA',
                'personB',
                'group',
                'items.media',
            ])
            ->orderByRaw("case when visibility = 'public_ready' then 0 else 1 end")
            ->orderByRaw("case
                when collection_type = 'must_have_delivery' then 0
                when collection_type = 'pair_best_of' then 1
                when collection_type = 'family_moment' then 2
                when collection_type = 'group_best_of' then 3
                else 4
            end")
            ->orderByDesc('generated_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, EventRelationalCollection>  $collections
     * @return array<string, mixed>
     */
    public function summarize(Collection $collections): array
    {
        $lastGeneratedAt = $collections
            ->map(fn (EventRelationalCollection $collection) => $collection->generated_at)
            ->filter()
            ->sort()
            ->last();

        return [
            'total_collections' => $collections->count(),
            'public_ready_collections' => $collections->where('visibility', 'public_ready')->count(),
            'internal_collections' => $collections->where('visibility', 'internal')->count(),
            'must_have_deliveries' => $collections->where('collection_type', 'must_have_delivery')->count(),
            'last_generated_at' => $lastGeneratedAt?->toIso8601String(),
        ];
    }
}
