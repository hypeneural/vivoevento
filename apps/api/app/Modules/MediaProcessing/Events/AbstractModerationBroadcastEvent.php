<?php

namespace App\Modules\MediaProcessing\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class AbstractModerationBroadcastEvent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 20;
    public int $backoff = 5;

    public function __construct(
        public int $organizationId,
        public array $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("organization.{$this->organizationId}.moderation")];
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
        Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
            ->error('moderation.broadcast_failed', [
                'event' => static::class,
                'broadcast_name' => method_exists($this, 'broadcastAs') ? $this->broadcastAs() : static::class,
                'queue' => $this->broadcastQueue(),
                'organization_id' => $this->organizationId,
                'media_id' => $this->payload['id'] ?? null,
                'exception_class' => $exception ? $exception::class : null,
                'message' => $exception?->getMessage(),
            ]);
    }
}
