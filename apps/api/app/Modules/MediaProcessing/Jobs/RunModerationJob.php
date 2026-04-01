<?php

namespace App\Modules\MediaProcessing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunModerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly int $eventMediaId,
    ) {
        $this->onQueue('media-process');
    }

    public function handle(): void
    {
        // 1. Apply moderation rules (auto-approve or keep pending)
        // 2. Update moderation_status
        // 3. If approved, dispatch PublishMediaJob
    }
}
