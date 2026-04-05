<?php

namespace App\Modules\Wall\Jobs;

use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\Wall\Services\WallDiagnosticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateWallDiagnosticsJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $uniqueFor = 10;

    public function __construct(
        public readonly int $eventWallSettingId,
    ) {
        $this->onQueue('analytics');
    }

    public function uniqueId(): string
    {
        return 'wall-diagnostics-'.$this->eventWallSettingId;
    }

    public function handle(WallDiagnosticsService $diagnostics): void
    {
        $settings = EventWallSetting::query()
            ->with('diagnosticSummary')
            ->find($this->eventWallSettingId);

        if (! $settings) {
            return;
        }

        $diagnostics->recalculateSummary($settings);
    }
}
