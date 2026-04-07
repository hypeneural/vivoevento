<?php

namespace App\Modules\FaceSearch\Console;

use App\Modules\FaceSearch\Services\CompreFaceSmokeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class RunCompreFaceSmokeCommand extends Command
{
    protected $signature = 'face-search:smoke-compreface
        {--manifest=tests/Fixtures/AI/local/vipsocial.manifest.json : Manifest path relative to base_path() or absolute}
        {--dry-run : Validate manifest and selected files without calling the provider}
        {--report-dir= : Optional absolute report directory override}';

    protected $description = 'Run the local CompreFace smoke workflow against a consented dataset manifest.';

    public function handle(CompreFaceSmokeService $service): int
    {
        try {
            $report = $service->run(
                manifestPath: (string) $this->option('manifest'),
                dryRun: (bool) $this->option('dry-run'),
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
        $directory = (string) ($this->option('report-dir') ?: storage_path('app/face-search-smoke'));

        File::ensureDirectoryExists($directory);

        $filename = sprintf(
            '%s-compreface-%s.json',
            now()->format('Ymd-His'),
            ($report['dry_run'] ?? false) ? 'dry-run' : 'real-run',
        );

        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }
}
