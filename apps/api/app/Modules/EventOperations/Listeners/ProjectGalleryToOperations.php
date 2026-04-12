<?php

namespace App\Modules\EventOperations\Listeners;

use App\Modules\EventOperations\Actions\AppendEventOperationEventAction;
use App\Modules\EventOperations\Support\EventOperationsEventMapper;
use App\Modules\MediaProcessing\Events\MediaPublished;
use Illuminate\Support\Arr;

class ProjectGalleryToOperations
{
    public function __construct(
        private readonly EventOperationsEventMapper $mapper,
        private readonly AppendEventOperationEventAction $append,
    ) {}

    public function handle(MediaPublished $event): void
    {
        $media = $event->resolveMedia();
        $mapped = $this->mapper->fromMediaPublishedToGallery($event);

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
