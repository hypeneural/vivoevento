<?php

namespace App\Modules\Gallery\Queries;

use App\Modules\Gallery\Models\EventGalleryRevision;
use Illuminate\Database\Eloquent\Collection;

class ListEventGalleryRevisionsQuery
{
    /**
     * @return Collection<int, EventGalleryRevision>
     */
    public function execute(int $eventId): Collection
    {
        return EventGalleryRevision::query()
            ->where('event_id', $eventId)
            ->with('creator:id,name')
            ->orderByDesc('version_number')
            ->orderByDesc('id')
            ->get();
    }
}
