<?php

namespace App\Modules\Wall\Events;

class PrivateWallLiveSnapshotUpdated extends AbstractPrivateWallImmediateBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'wall.runtime.snapshot.updated';
    }
}
