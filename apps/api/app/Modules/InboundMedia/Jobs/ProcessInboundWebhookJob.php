<?php

namespace App\Modules\InboundMedia\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessInboundWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $provider,
        public readonly array $payload,
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        // 1. Validate webhook signature
        // 2. Log to channel_webhook_logs
        // 3. Dispatch NormalizeInboundMessageJob
    }
}
