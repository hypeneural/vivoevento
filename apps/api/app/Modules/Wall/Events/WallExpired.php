<?php

namespace App\Modules\Wall\Events;

/**
 * Fired when the wall reaches its expiration time or is manually expired.
 * The wall player should show an expired/ended screen.
 * Payload: {reason, expired_at}
 */
class WallExpired extends AbstractWallBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'wall.expired';
    }
}
