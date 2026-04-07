<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\FaceSearch\Jobs\IndexMediaFacesJob;
use Illuminate\Support\Facades\Process;

class ArtisanQueueFaceIndexLaneExecutor implements FaceIndexLaneExecutorInterface
{
    /**
     * @param array<int, int> $eventMediaIds
     * @return array<string, mixed>
     */
    public function execute(array $eventMediaIds, string $queueName = 'face-index'): array
    {
        foreach ($eventMediaIds as $eventMediaId) {
            IndexMediaFacesJob::dispatch($eventMediaId)->onQueue($queueName);
        }

        $startedAt = microtime(true);
        $memoryLimitMb = (int) config('horizon.defaults.supervisor-face-index.memory', 512);
        $result = Process::path(base_path())
            ->timeout(300)
            ->run([
                PHP_BINARY,
                'artisan',
                'queue:work',
                'redis',
                '--queue=' . $queueName,
                '--stop-when-empty',
                '--sleep=0',
                '--tries=1',
                '--timeout=120',
                '--memory=' . max(128, $memoryLimitMb),
                '--max-jobs=' . max(1, count($eventMediaIds)),
            ]);

        return [
            'mode' => 'subprocess_queue_work',
            'queue_name' => $queueName,
            'exit_code' => (int) ($result->exitCode() ?? 1),
            'memory_limit_mb' => max(128, $memoryLimitMb),
            'worker_output' => trim($result->output() . PHP_EOL . $result->errorOutput()),
            'wall_clock_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }
}
