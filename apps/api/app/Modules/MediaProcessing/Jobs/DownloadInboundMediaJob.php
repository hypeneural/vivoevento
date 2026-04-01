<?php

namespace App\Modules\MediaProcessing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DownloadInboundMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int $inboundMessageId,
    ) {
        $this->onQueue('media-download');
    }

    public function handle(): void
    {
        // 1. Load inbound message
        // 2. Download media from URL via MediaDownloadService
        // 3. Create event_media record
        // 4. Dispatch GenerateMediaVariantsJob
    }
}
