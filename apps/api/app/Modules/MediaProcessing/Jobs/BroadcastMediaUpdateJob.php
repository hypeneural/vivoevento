<?php

namespace App\Modules\MediaProcessing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BroadcastMediaUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $eventMediaId,
        public readonly string $action = 'published',
    ) {
        $this->onQueue('media-publish');
    }

    public function handle(): void
    {
        // 1. Load event_media with event
        // 2. Broadcast to event.{id}.gallery channel
        // 3. Broadcast to event.{id}.wall channel
    }
}
