<?php

namespace App\Modules\Wall\Events;

/**
 * Fired when a media item is updated (e.g., variant generated, metadata changed).
 * The wall player should replace the existing slide with the updated URL.
 * Payload: {id, url, type, sender_name, caption, is_featured, created_at}
 */
class WallMediaUpdated extends AbstractWallBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'wall.media.updated';
    }
}
