<?php

use App\Modules\FaceSearch\Jobs\IndexMediaFacesJob;
use App\Modules\FaceSearch\Services\ArtisanQueueFaceIndexLaneExecutor;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Process;

it('dispatches the batch to the provided queue and runs a dedicated queue worker subprocess', function () {
    Bus::fake();
    Process::fake([
        '*' => Process::result("worker ok\n", '', 0),
    ]);

    $report = (new ArtisanQueueFaceIndexLaneExecutor)->execute([101, 202, 303], 'face-index-benchmark-local');

    Bus::assertDispatched(IndexMediaFacesJob::class, 3);
    Bus::assertDispatched(IndexMediaFacesJob::class, fn (IndexMediaFacesJob $job) => $job->eventMediaId === 101 && $job->queue === 'face-index-benchmark-local');
    Bus::assertDispatched(IndexMediaFacesJob::class, fn (IndexMediaFacesJob $job) => $job->eventMediaId === 202 && $job->queue === 'face-index-benchmark-local');
    Bus::assertDispatched(IndexMediaFacesJob::class, fn (IndexMediaFacesJob $job) => $job->eventMediaId === 303 && $job->queue === 'face-index-benchmark-local');

    Process::assertRan(function ($process, $result) {
        return $process->path === base_path()
            && $process->command === [
                PHP_BINARY,
                'artisan',
                'queue:work',
                'redis',
                '--queue=face-index-benchmark-local',
                '--stop-when-empty',
                '--sleep=0',
                '--tries=1',
                '--timeout=120',
                '--memory=512',
                '--max-jobs=3',
            ]
            && $result->successful();
    });

    expect($report['mode'])->toBe('subprocess_queue_work')
        ->and($report['queue_name'])->toBe('face-index-benchmark-local')
        ->and($report['exit_code'])->toBe(0)
        ->and($report['memory_limit_mb'])->toBe(512)
        ->and($report['worker_output'])->toContain('worker ok');
});
