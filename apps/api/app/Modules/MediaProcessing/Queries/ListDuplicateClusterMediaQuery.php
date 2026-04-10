<?php

namespace App\Modules\MediaProcessing\Queries;

use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Database\Eloquent\Collection;

class ListDuplicateClusterMediaQuery
{
    public function __construct(
        private readonly EventMedia $eventMedia,
    ) {}

    public function get(): Collection
    {
        $duplicateGroupKey = $this->eventMedia->duplicate_group_key;

        if (! is_string($duplicateGroupKey) || trim($duplicateGroupKey) === '') {
            return new Collection();
        }

        return EventMedia::query()
            ->where('event_id', $this->eventMedia->event_id)
            ->where('duplicate_group_key', $duplicateGroupKey)
            ->with(['event', 'variants', 'inboundMessage'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }
}
