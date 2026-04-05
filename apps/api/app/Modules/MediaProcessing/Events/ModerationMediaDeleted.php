<?php

namespace App\Modules\MediaProcessing\Events;

class ModerationMediaDeleted extends AbstractModerationBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'moderation.media.deleted';
    }
}
