<?php

namespace App\Modules\EventOperations\Listeners;

use App\Modules\EventOperations\Actions\AppendEventOperationEventAction;
use App\Modules\EventOperations\Support\EventOperationsEventMapper;
use App\Modules\MediaProcessing\Events\MediaPublished;
use App\Modules\MediaProcessing\Events\MediaRejected;
use Illuminate\Support\Arr;

class ProjectModerationToOperations
{
    public function __construct(
        private readonly EventOperationsEventMapper $mapper,
        private readonly AppendEventOperationEventAction $append,
    ) {}

    public function handleApproved(MediaPublished $event): void
    {
        $media = $event->resolveMedia();
        $mapped = $this->mapper->fromMediaPublishedToModeration($event);

        if (! $media || ! $mapped) {
            return;
        }

        $parentEvent = $media->event()->first();

        if (! $parentEvent) {
            return;
        }

        $this->append->execute($parentEvent, Arr::except($mapped, ['priority']));
    }

    public function handleRejected(MediaRejected $event): void
    {
        $media = $event->resolveMedia();
        $mapped = $this->mapper->fromMediaRejected($event);

        if (! $media || ! $mapped) {
            return;
        }

        $parentEvent = $media->event()->first();

        if (! $parentEvent) {
            return;
        }

        $this->append->execute($parentEvent, Arr::except($mapped, ['priority']));
    }
}
