<?php

namespace App\Modules\EventOperations\Actions;

use App\Modules\EventOperations\Models\EventOperationSnapshot;
use App\Modules\EventOperations\Support\EventOperationsSnapshotBuilder;
use App\Modules\Events\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RebuildEventOperationsSnapshotAction
{
    public function __construct(
        private readonly EventOperationsSnapshotBuilder $snapshotBuilder,
    ) {}

    public function execute(Event $event): EventOperationSnapshot
    {
        return DB::transaction(function () use ($event) {
            $currentSnapshot = EventOperationSnapshot::query()
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->first();

            $nextSnapshotVersion = (int) (($currentSnapshot?->snapshot_version ?? 0) + 1);
            $room = $this->snapshotBuilder->buildForEvent($event, $nextSnapshotVersion);
            $roomPayload = $room->toArray();

            unset(
                $roomPayload['schema_version'],
                $roomPayload['snapshot_version'],
                $roomPayload['timeline_cursor'],
                $roomPayload['event_sequence'],
                $roomPayload['server_time'],
            );

            return EventOperationSnapshot::query()->updateOrCreate(
                [
                    'event_id' => $event->id,
                ],
                [
                    'schema_version' => $room->schema_version,
                    'snapshot_version' => $room->snapshot_version,
                    'latest_event_sequence' => $room->event_sequence,
                    'timeline_cursor' => $room->timeline_cursor,
                    'snapshot_json' => $roomPayload,
                    'server_time' => Carbon::parse($room->server_time),
                    'updated_at' => now(),
                ],
            );
        });
    }
}
