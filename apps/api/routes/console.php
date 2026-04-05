<?php

use App\Modules\Wall\Jobs\PruneWallRuntimeSnapshotsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->withoutOverlapping();

collect(config('observability.queue_busy_thresholds', []))
    ->each(function (int $threshold, string $queue): void {
        Schedule::command(sprintf(
            'queue:monitor %s:%s --max=%d',
            (string) config('observability.queue_busy_connection', 'redis'),
            $queue,
            $threshold,
        ))
            ->everyMinute()
            ->withoutOverlapping();
    });

Schedule::job(new PruneWallRuntimeSnapshotsJob())
    ->daily()
    ->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
