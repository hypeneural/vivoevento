<?php

namespace App\Modules\Wall\Events;

/**
 * Fired when a new media item is published and should appear on the wall.
 * Payload: {id, url, type, sender_name, caption, is_featured, created_at}
 */
class WallMediaPublished extends AbstractWallBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'wall.media.published';
    }
}
