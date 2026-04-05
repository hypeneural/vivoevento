<?php

namespace App\Modules\Wall\Events;

/**
 * Fired when wall settings are updated by admin.
 * The wall player should apply new layout, interval, etc. without reloading.
 * Payload: {interval_ms, queue_limit, layout, transition_effect, background_url, ...}
 */
class WallSettingsUpdated extends AbstractWallImmediateBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'wall.settings.updated';
    }
}
