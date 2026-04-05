<?php

namespace App\Modules\Play\Events;

class PlayLeaderboardUpdated extends AbstractPlayImmediateBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'play.leaderboard.updated';
    }
}
