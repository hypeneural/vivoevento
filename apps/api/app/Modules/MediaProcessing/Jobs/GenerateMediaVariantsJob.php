<?php

namespace App\Modules\MediaProcessing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateMediaVariantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly int $eventMediaId,
    ) {
        $this->onQueue('media-process');
    }

    public function handle(): void
    {
        // 1. Load event_media
        // 2. Generate variants: thumb, gallery, wall, memory_card, puzzle
        // 3. Save to event_media_variants
        // 4. Update processing_status
        // 5. Dispatch RunModerationJob
    }
}
