<?php

namespace App\Modules\Wall\Listeners;

use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Events\AbstractMediaPipelineEvent;
use App\Modules\Wall\Services\WallBroadcasterService;

/**
 * Listen for when media is published in the pipeline.
 * If the event has an active wall, broadcast the new media.
 */
class BroadcastWallOnMediaPublished
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

        if ($media->moderation_status !== ModerationStatus::Approved) {
            return;
        }

        if ($media->publication_status !== PublicationStatus::Published) {
            return;
        }

        $media->loadMissing(['variants', 'inboundMessage']);

        $this->broadcaster->broadcastNewMedia($media);
    }
}
