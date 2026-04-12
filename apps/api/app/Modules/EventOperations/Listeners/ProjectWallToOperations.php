<?php

namespace App\Modules\EventOperations\Listeners;

use App\Modules\EventOperations\Actions\AppendEventOperationEventAction;
use App\Modules\EventOperations\Support\EventOperationsEventMapper;
use App\Modules\Events\Models\Event;
use App\Modules\Wall\Events\WallDiagnosticsUpdated;
use App\Modules\Wall\Events\WallMediaPublished;
use Illuminate\Support\Arr;

class ProjectWallToOperations
{
    public function __construct(
        private readonly EventOperationsEventMapper $mapper,
        private readonly AppendEventOperationEventAction $append,
    ) {}

    public function handleMediaPublished(WallMediaPublished $event): void
    {
        $mapped = $this->mapper->fromWallMediaPublished($event);

        if (! $mapped) {
            return;
        }

        $parentEvent = Event::query()
            ->whereHas('wallSettings', fn ($query) => $query->where('wall_code', $event->wallCode))
            ->first();

        if (! $parentEvent) {
            return;
        }

        $this->append->execute($parentEvent, Arr::except($mapped, ['priority']));
    }

    public function handleDiagnosticsUpdated(WallDiagnosticsUpdated $event): void
    {
        $mapped = $this->mapper->fromWallDiagnosticsUpdated($event);
        $parentEvent = Event::query()->find($event->eventId);

        if (! $parentEvent || ! $mapped) {
            return;
        }

        $this->append->execute($parentEvent, Arr::except($mapped, ['priority']));
    }
}
