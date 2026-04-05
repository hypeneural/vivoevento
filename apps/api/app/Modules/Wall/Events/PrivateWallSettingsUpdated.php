<?php

namespace App\Modules\Wall\Events;

class PrivateWallSettingsUpdated extends AbstractPrivateWallImmediateBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'wall.settings.updated';
    }
}
