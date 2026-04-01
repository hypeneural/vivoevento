<?php

namespace App\Modules\Wall\Listeners;

use App\Modules\MediaProcessing\Events\AbstractMediaPipelineEvent;
use App\Modules\Wall\Services\WallBroadcasterService;

/**
 * Listen for when media is deleted or rejected.
 * Broadcasts removal so the wall player drops the slide.
 */
class BroadcastWallOnMediaDeleted
{
    public function __construct(
        private readonly WallBroadcasterService $broadcaster,
    ) {}

    public function handle(AbstractMediaPipelineEvent $event): void
    {
        $media = $event->resolveMedia(withTrashed: true);

        if (! $media) {
            return;
        }

        $this->broadcaster->broadcastMediaDeleted($media);
    }
}
