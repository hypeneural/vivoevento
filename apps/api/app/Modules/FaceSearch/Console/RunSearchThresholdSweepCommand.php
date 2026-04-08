<?php

namespace App\Modules\FaceSearch\Console;

use App\Modules\FaceSearch\Services\FaceSearchThresholdSweepService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class RunSearchThresholdSweepCommand extends Command
{
    protected $signature = 'face-search:sweep-search-threshold
        {--smoke-report= : Path to a real CompreFace smoke JSON report}
        {--thresholds=0.30,0.35,0.40,0.45,0.50,0.55,0.60 : Comma-separated pgvector cosine-distance thresholds}
        {--strategies=exact,ann : Comma-separated list of strategies to benchmark}
        {--top-k=5 : Number of matches considered for hit-rate}
        {--report-dir= : Optional absolute report directory override}';

    protected $description = 'Sweep FaceSearch pgvector search_threshold values using a real smoke report as source.';

    public function handle(FaceSearchThresholdSweepService $service): int
    {
        try {
            $report = $service->run(
                smokeReportPath: (string) $this->option('smoke-report'),
                thresholds: array_values(array_filter(array_map('trim', explode(',', (string) $this->option('thresholds'))))),
                strategies: array_values(array_filter(array_map('trim', explode(',', (string) $this->option('strategies'))))),
                topK: (int) $this->option('top-k'),
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
        $directory = (string) ($this->option('report-dir') ?: storage_path('app/face-search-threshold-sweep'));

        File::ensureDirectoryExists($directory);

        $filename = sprintf('%s-face-search-threshold-sweep.json', now()->format('Ymd-His'));
        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }
}
