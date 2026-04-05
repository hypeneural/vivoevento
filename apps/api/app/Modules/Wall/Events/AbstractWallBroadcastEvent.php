<?php

namespace App\Modules\Wall\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    public int $tries = 3;
    public int $timeout = 15;
    public int $backoff = 5;

    public function __construct(
        public string $wallCode,
        public array $payload,
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

    public function failed(?Throwable $exception = null): void
    {
        Log::channel((string) config('observability.wall_log_channel', config('logging.default')))
            ->error('wall.broadcast_failed', [
                'event' => static::class,
                'broadcast_name' => method_exists($this, 'broadcastAs') ? $this->broadcastAs() : static::class,
                'queue' => $this->broadcastQueue(),
                'wall_code' => $this->wallCode,
                'media_id' => $this->payload['id'] ?? null,
                'exception_class' => $exception ? $exception::class : null,
                'message' => $exception?->getMessage(),
            ]);
    }
}
