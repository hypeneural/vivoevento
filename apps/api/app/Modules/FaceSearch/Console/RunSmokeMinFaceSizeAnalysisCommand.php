<?php

namespace App\Modules\FaceSearch\Console;

use App\Modules\FaceSearch\Services\SmokeMinFaceSizeAnalysisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class RunSmokeMinFaceSizeAnalysisCommand extends Command
{
    protected $signature = 'face-search:analyze-smoke-min-face-size
        {--smoke-report= : Path to a real CompreFace smoke JSON report}
        {--thresholds=16,24,32,40,48,64,96 : Comma-separated min_face_size_px values}
        {--min-retained-rate=1.0 : Minimum retained successful-entry rate for recommendation}
        {--report-dir= : Optional absolute report directory override}';

    protected $description = 'Analyze selected-face retention by min_face_size_px using a real smoke report.';

    public function handle(SmokeMinFaceSizeAnalysisService $service): int
    {
        try {
            $report = $service->run(
                smokeReportPath: (string) $this->option('smoke-report'),
                thresholds: array_values(array_filter(array_map('trim', explode(',', (string) $this->option('thresholds'))))),
                minRetainedRate: (float) $this->option('min-retained-rate'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $path = $this->storeReport($report);

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->newLine();
        $this->info("Report saved to {$path}");

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function storeReport(array $report): string
    {
        $directory = (string) ($this->option('report-dir') ?: storage_path('app/face-search-smoke-min-face-size-analysis'));

        File::ensureDirectoryExists($directory);

        $filename = sprintf('%s-face-search-smoke-min-face-size-analysis.json', now()->format('Ymd-His-u'));
        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }
}
