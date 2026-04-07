<?php

namespace App\Modules\FaceSearch\Console;

use App\Modules\FaceSearch\Services\FaceSearchBenchmarkService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class RunFaceSearchBenchmarkCommand extends Command
{
    protected $signature = 'face-search:benchmark
        {--smoke-report= : Path to a real CompreFace smoke JSON report}
        {--strategies=exact,ann : Comma-separated list of strategies to benchmark}
        {--top-k=5 : Number of matches considered for hit-rate}
        {--threshold= : Optional distance threshold override}
        {--report-dir= : Optional absolute report directory override}';

    protected $description = 'Run the FaceSearch benchmark using a real CompreFace smoke report as source.';

    public function handle(FaceSearchBenchmarkService $service): int
    {
        try {
            $report = $service->run(
                smokeReportPath: (string) $this->option('smoke-report'),
                strategies: array_values(array_filter(array_map('trim', explode(',', (string) $this->option('strategies'))))),
                topK: (int) $this->option('top-k'),
                threshold: $this->option('threshold') !== null ? (float) $this->option('threshold') : null,
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
        $directory = (string) ($this->option('report-dir') ?: storage_path('app/face-search-benchmark'));

        File::ensureDirectoryExists($directory);

        $filename = sprintf('%s-face-search-benchmark.json', now()->format('Ymd-His'));
        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }
}
