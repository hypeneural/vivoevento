<?php

namespace App\Modules\EventOperations\Listeners;

use App\Modules\EventOperations\Actions\AppendEventOperationEventAction;
use App\Modules\EventOperations\Support\EventOperationsEventMapper;
use App\Modules\MediaProcessing\Events\MediaVariantsGenerated;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Arr;

class ProjectMediaRunsToOperations
{
    public function __construct(
        private readonly EventOperationsEventMapper $mapper,
        private readonly AppendEventOperationEventAction $append,
    ) {}

    public function handleDownloadedMedia(EventMedia $media): void
    {
        $event = $media->event()->first();
        $mapped = $this->mapper->fromEventMediaCreated($media);

        if (! $event || ! $mapped) {
            return;
        }

        $this->append->execute($event, Arr::except($mapped, ['priority']));
    }

    public function handleVariantsGenerated(MediaVariantsGenerated $event): void
    {
        $media = $event->resolveMedia();
        $mapped = $this->mapper->fromMediaVariantsGenerated($event);

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
