<?php

namespace App\Modules\Wall\Events;

class PrivateWallExpired extends AbstractPrivateWallImmediateBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'wall.expired';
    }
}
