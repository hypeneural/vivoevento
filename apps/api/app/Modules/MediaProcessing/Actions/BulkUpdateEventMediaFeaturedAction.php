<?php

namespace App\Modules\MediaProcessing\Actions;

use Illuminate\Support\Collection;

class BulkUpdateEventMediaFeaturedAction
{
    public function __construct(
        private readonly UpdateEventMediaFeaturedAction $updateEventMediaFeatured,
    ) {}

    public function execute(Collection $mediaItems, bool $isFeatured): Collection
    {
        return $mediaItems
            ->map(fn ($media) => $this->updateEventMediaFeatured->execute($media, $isFeatured))
            ->values();
    }
}
