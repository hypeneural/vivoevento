<?php

namespace App\Modules\Wall\Events;

/**
 * Fired when a media item is removed from the wall.
 * The wall player should remove the slide from its queue.
 * Payload: {id}
 */
class WallMediaDeleted extends AbstractWallBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'wall.media.deleted';
    }
}
