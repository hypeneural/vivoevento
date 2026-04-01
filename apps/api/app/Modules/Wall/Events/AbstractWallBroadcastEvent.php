<?php

namespace App\Modules\Wall\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for all wall broadcast events.
 *
 * Uses the dedicated broadcasts queue so wall updates do not compete with
 * request handling or heavier media pipeline work.
 */
abstract class AbstractWallBroadcastEvent implements ShouldBroadcast, ShouldDispatchAfterCommit
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

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
