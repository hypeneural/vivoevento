<?php

namespace App\Modules\EventOperations\Actions;

use App\Modules\EventOperations\Data\EventOperationsRoomData;
use App\Modules\EventOperations\Models\EventOperationSnapshot;
use App\Modules\Events\Models\Event;

class BuildEventOperationsRoomAction
{
    public function __construct(
        private readonly RebuildEventOperationsSnapshotAction $rebuildSnapshot,
    ) {}

    public function execute(Event $event): EventOperationsRoomData
    {
        $snapshot = EventOperationSnapshot::query()
            ->where('event_id', $event->id)
            ->first();

        if (! $snapshot) {
            $snapshot = $this->rebuildSnapshot->execute($event);
        }

        /** @var array<string, mixed> $payload */
        $payload = is_array($snapshot->snapshot_json) ? $snapshot->snapshot_json : [];

        return new EventOperationsRoomData(
            schema_version: (int) $snapshot->schema_version,
            snapshot_version: (int) $snapshot->snapshot_version,
            timeline_cursor: $snapshot->timeline_cursor,
            event_sequence: (int) $snapshot->latest_event_sequence,
            server_time: ($snapshot->server_time ?? now())->toIso8601String(),
            event: (array) ($payload['event'] ?? []),
            health: (array) ($payload['health'] ?? []),
            connection: (array) ($payload['connection'] ?? []),
            counters: (array) ($payload['counters'] ?? []),
            stations: array_values((array) ($payload['stations'] ?? [])),
            alerts: array_values((array) ($payload['alerts'] ?? [])),
            wall: (array) ($payload['wall'] ?? []),
            timeline: array_values((array) ($payload['timeline'] ?? [])),
        );
    }
}
