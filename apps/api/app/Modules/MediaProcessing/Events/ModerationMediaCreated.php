<?php

namespace App\Modules\MediaProcessing\Events;

class ModerationMediaCreated extends AbstractModerationBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'moderation.media.created';
    }
}
