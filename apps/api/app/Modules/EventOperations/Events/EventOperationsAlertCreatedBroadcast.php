<?php

namespace App\Modules\EventOperations\Events;

use App\Modules\EventOperations\Data\EventOperationsDeltaData;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventOperationsAlertCreatedBroadcast implements ShouldBroadcastNow, ShouldDispatchAfterCommit
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
        return 'operations.alert.created';
    }

    public function broadcastWith(): array
    {
        return $this->basePayload() + [
            'alert' => $this->delta->alert,
        ];
    }

    public function broadcastWhen(): bool
    {
        return $this->delta->alert !== null;
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
