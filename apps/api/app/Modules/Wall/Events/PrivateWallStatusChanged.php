<?php

namespace App\Modules\Wall\Events;

class PrivateWallStatusChanged extends AbstractPrivateWallImmediateBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'wall.status.changed';
    }
}
