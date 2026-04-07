<?php

namespace App\Modules\MediaIntelligence\Console;

use App\Modules\MediaIntelligence\Services\OpenRouterSmokeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class RunOpenRouterSmokeCommand extends Command
{
    protected $signature = 'media-intelligence:smoke-openrouter
        {--manifest=tests/Fixtures/AI/local/vipsocial.manifest.json : Manifest path relative to base_path() or absolute}
        {--entry-id= : Optional entry id from the dataset manifest}
        {--model= : Optional fixed OpenRouter model override}
        {--report-dir= : Optional absolute report directory override}';

    protected $description = 'Run the real OpenRouter smoke workflow using the local consented dataset.';

    public function handle(OpenRouterSmokeService $service): int
    {
        try {
            $report = $service->run(
                manifestPath: (string) $this->option('manifest'),
                entryId: $this->option('entry-id') ? (string) $this->option('entry-id') : null,
                modelOverride: $this->option('model') ? (string) $this->option('model') : null,
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
        $directory = (string) ($this->option('report-dir') ?: storage_path('app/media-intelligence-smoke'));

        File::ensureDirectoryExists($directory);

        $filename = sprintf(
            '%s-openrouter-real-run.json',
            now()->format('Ymd-His'),
        );

        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }
}
