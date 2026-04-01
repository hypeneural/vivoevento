<?php

namespace App\Modules\Wall\Listeners;

use App\Modules\MediaProcessing\Events\AbstractMediaPipelineEvent;
use App\Modules\Wall\Services\WallBroadcasterService;

/**
 * Listen for when media variants are generated (e.g. wall-optimized).
 * Broadcasts updated URL so the wall player swaps to the better quality.
 */
class BroadcastWallOnMediaUpdated
{
    public function __construct(
        private readonly WallBroadcasterService $broadcaster,
    ) {}

    public function handle(AbstractMediaPipelineEvent $event): void
    {
        $media = $event->resolveMedia();

        if (! $media) {
            return;
        }

        $media->loadMissing(['variants', 'inboundMessage']);

        $this->broadcaster->broadcastMediaUpdated($media);
    }
}
