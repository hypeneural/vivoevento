<?php

namespace App\Modules\Play\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class AbstractPlayImmediateBroadcastEvent implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $gameUuid,
        public readonly array $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("play.game.{$this->gameUuid}")];
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
