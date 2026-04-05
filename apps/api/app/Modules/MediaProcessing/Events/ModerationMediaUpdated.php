<?php

namespace App\Modules\MediaProcessing\Events;

class ModerationMediaUpdated extends AbstractModerationBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'moderation.media.updated';
    }
}
