<?php

namespace App\Modules\MediaProcessing\Listeners;

use App\Modules\MediaProcessing\Events\MediaDeleted;
use App\Modules\MediaProcessing\Jobs\CleanupDeletedMediaArtifactsJob;

class QueueCleanupDeletedMediaArtifactsOnMediaDeleted
{
    public function handle(MediaDeleted $event): void
    {
        CleanupDeletedMediaArtifactsJob::dispatch($event->eventMediaId);
    }
}
