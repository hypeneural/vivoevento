<?php

namespace App\Modules\MediaProcessing\Actions;

use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Collection;

class BulkUpdateEventMediaPinnedAction
{
    public function __construct(
        private readonly UpdateEventMediaPinnedAction $updateEventMediaPinned,
    ) {}

    public function execute(Collection $mediaItems, bool $isPinned): Collection
    {
        return $mediaItems
            ->map(fn (EventMedia $media) => $this->updateEventMediaPinned->execute($media, $isPinned))
            ->values();
    }
}
