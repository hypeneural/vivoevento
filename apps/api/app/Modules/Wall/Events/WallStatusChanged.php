<?php

namespace App\Modules\Wall\Events;

/**
 * Fired when wall status changes (live, paused, stopped).
 * The wall player should show/hide content accordingly.
 * Payload: {status, reason, updated_at}
 */
class WallStatusChanged extends AbstractWallImmediateBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'wall.status.changed';
    }
}
