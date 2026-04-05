<?php

namespace App\Modules\Wall\Jobs;

use App\Modules\Wall\Services\WallDiagnosticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PruneWallRuntimeSnapshotsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
        $this->onQueue('analytics');
    }

    public function handle(WallDiagnosticsService $diagnostics): void
    {
        $diagnostics->pruneStaleSnapshots();
    }
}
