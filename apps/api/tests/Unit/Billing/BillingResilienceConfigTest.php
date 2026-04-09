<?php

use App\Modules\Billing\Jobs\ProcessBillingWebhookJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;

it('configures the billing webhook processor as a unique job on the billing queue', function () {
    $job = new ProcessBillingWebhookJob(101);

    expect($job)
        ->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe('billing-webhook-101')
        ->and($job->uniqueFor)->toBe(300)
        ->and($job->queue)->toBe('billing');
});
