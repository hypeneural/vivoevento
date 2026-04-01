<?php

namespace App\Modules\InboundMedia\Jobs;

use App\Modules\InboundMedia\Models\ChannelWebhookLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NormalizeInboundMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $webhookLogId,
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        // 1. Load webhook log
        // 2. Extract message type, sender, media URL
        // 3. Resolve event via channel
        // 4. Create inbound_message record
        // 5. If has media, dispatch DownloadInboundMediaJob
    }
}
