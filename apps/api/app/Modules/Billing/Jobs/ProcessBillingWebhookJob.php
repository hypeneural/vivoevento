<?php

namespace App\Modules\Billing\Jobs;

use App\Modules\Billing\Actions\ProcessBillingWebhookAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessBillingWebhookJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $gatewayEventId,
    ) {
        $this->onQueue('billing');
    }

    public function uniqueId(): string
    {
        return 'billing-webhook-'.$this->gatewayEventId;
    }

    public function handle(ProcessBillingWebhookAction $action): void
    {
        $action->executeRecorded($this->gatewayEventId);
    }
}
