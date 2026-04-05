<?php

namespace App\Modules\Wall\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for operational wall events that should be visible immediately
 * after the transaction is committed.
 */
abstract class AbstractWallImmediateBroadcastEvent implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $wallCode,
        public readonly array $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("wall.{$this->wallCode}")];
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
