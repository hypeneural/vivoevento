<?php

namespace App\Modules\Wall\Events;

class WallPlayerCommanded extends AbstractWallImmediateBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'wall.player.command';
    }
}
