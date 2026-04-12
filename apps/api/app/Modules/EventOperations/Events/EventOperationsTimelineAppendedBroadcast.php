<?php

namespace App\Modules\EventOperations\Events;

use App\Modules\EventOperations\Data\EventOperationsDeltaData;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventOperationsTimelineAppendedBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $eventId,
        public readonly EventOperationsDeltaData $delta,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("event.{$this->eventId}.operations")];
    }

    public function broadcastAs(): string
    {
        return 'operations.timeline.appended';
    }

    public function broadcastWith(): array
    {
        return $this->basePayload() + [
            'timeline_entry' => $this->delta->timeline_entry,
        ];
    }

    public function broadcastWhen(): bool
    {
        return $this->delta->timeline_entry !== null;
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(): array
    {
        return [
            'schema_version' => $this->delta->schema_version,
            'snapshot_version' => $this->delta->snapshot_version,
            'timeline_cursor' => $this->delta->timeline_cursor,
            'event_sequence' => $this->delta->event_sequence,
            'server_time' => $this->delta->server_time,
            'resync_required' => $this->delta->resync_required,
        ];
    }
}
