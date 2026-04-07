<?php

namespace App\Modules\FaceSearch\Console;

use App\Modules\FaceSearch\Services\FaceIndexLaneThroughputService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class RunFaceIndexLaneThroughputCommand extends Command
{
    protected $signature = 'face-search:lane-throughput
        {--manifest=tests/Fixtures/AI/local/vipsocial.manifest.json : Path to a consented local manifest}
        {--report-dir= : Optional absolute report directory override}
        {--keep-artifacts : Keep temporary DB/storage artifacts after the run}';

    protected $description = 'Measure the real face-index lane throughput using queued IndexMediaFacesJob workers.';

    public function handle(FaceIndexLaneThroughputService $service): int
    {
        try {
            $report = $service->run(
                manifestPath: (string) $this->option('manifest'),
                cleanup: ! (bool) $this->option('keep-artifacts'),
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
        $directory = (string) ($this->option('report-dir') ?: storage_path('app/face-search-lane-throughput'));

        File::ensureDirectoryExists($directory);

        $filename = sprintf('%s-face-index-lane-throughput.json', now()->format('Ymd-His'));
        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }
}
