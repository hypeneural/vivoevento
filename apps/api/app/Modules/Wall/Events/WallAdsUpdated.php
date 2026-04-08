<?php

namespace App\Modules\Wall\Events;

/**
 * Fired when wall ads are created, deleted, or reordered.
 * The wall player should update its ad queue without reloading.
 * Payload: { ads: WallAdItem[] }
 */
class WallAdsUpdated extends AbstractWallImmediateBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'wall.ads.updated';
    }
}
