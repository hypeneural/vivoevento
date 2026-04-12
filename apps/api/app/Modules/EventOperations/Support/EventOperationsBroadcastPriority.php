<?php

namespace App\Modules\EventOperations\Support;

use App\Modules\EventOperations\Data\EventOperationsDeltaData;

class EventOperationsBroadcastPriority
{
    public function shouldBroadcastTimeline(EventOperationsDeltaData $delta): bool
    {
        return $delta->timeline_entry !== null;
    }

    public function shouldBroadcastStationDelta(EventOperationsDeltaData $delta): bool
    {
        return $delta->station_delta !== null
            && $delta->broadcast_priority !== EventOperationsAttentionPriority::TIMELINE_COALESCIBLE;
    }

    public function shouldBroadcastAlert(EventOperationsDeltaData $delta): bool
    {
        return $delta->alert !== null
            && $this->usesImmediate($delta->broadcast_priority);
    }

    public function shouldBroadcastHealth(EventOperationsDeltaData $delta): bool
    {
        return $delta->health !== null
            && $this->usesImmediate($delta->broadcast_priority);
    }

    public function usesImmediate(string $priority): bool
    {
        return $priority === EventOperationsAttentionPriority::CRITICAL_IMMEDIATE;
    }
}
