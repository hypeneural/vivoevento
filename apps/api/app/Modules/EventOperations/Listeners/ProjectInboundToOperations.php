<?php

namespace App\Modules\EventOperations\Listeners;

use App\Modules\EventOperations\Actions\AppendEventOperationEventAction;
use App\Modules\EventOperations\Support\EventOperationsEventMapper;
use App\Modules\InboundMedia\Models\InboundMessage;
use Illuminate\Support\Arr;

class ProjectInboundToOperations
{
    public function __construct(
        private readonly EventOperationsEventMapper $mapper,
        private readonly AppendEventOperationEventAction $append,
    ) {}

    public function handle(InboundMessage $message): void
    {
        $event = $message->event()->first();
        $mapped = $this->mapper->fromInboundMessage($message);

        if (! $event || ! $mapped) {
            return;
        }

        $this->append->execute($event, Arr::except($mapped, ['priority']));
    }
}
