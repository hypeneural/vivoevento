<?php

namespace App\Modules\Wall\Events;

class WallDiagnosticsUpdated extends AbstractPrivateWallImmediateBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'wall.diagnostics.updated';
    }
}
