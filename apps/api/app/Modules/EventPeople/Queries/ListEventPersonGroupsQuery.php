<?php

namespace App\Modules\EventPeople\Queries;

use App\Modules\EventPeople\Models\EventPersonGroup;
use App\Modules\Events\Models\Event;
use Illuminate\Database\Eloquent\Collection;

class ListEventPersonGroupsQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function get(Event $event, array $filters = []): Collection
    {
        $query = EventPersonGroup::query()
            ->forEvent($event->id)
            ->with([
                'groupStat',
                'groupMediaStat',
                'memberships' => fn ($membershipQuery) => $membershipQuery
                    ->orderByDesc('status')
                    ->orderBy('role_label')
                    ->orderBy('event_person_id'),
                'memberships.person.mediaStats',
                'memberships.person.primaryReferencePhoto',
            ])
            ->orderByDesc('importance_rank')
            ->orderBy('display_name');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['group_type'])) {
            $query->where('group_type', $filters['group_type']);
        }

        if (! empty($filters['search'])) {
            $term = mb_strtolower((string) $filters['search']);
            $query->whereRaw('LOWER(display_name) LIKE ?', ["%{$term}%"]);
        }

        if (! empty($filters['person_id'])) {
            $query->whereHas('memberships', fn ($membershipQuery) => $membershipQuery
                ->where('event_person_id', (int) $filters['person_id'])
                ->where('status', 'active'));
        }

        return $query->get();
    }
}
