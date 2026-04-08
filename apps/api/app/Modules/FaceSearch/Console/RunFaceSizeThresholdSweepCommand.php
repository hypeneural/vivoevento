<?php

namespace App\Modules\FaceSearch\Console;

use App\Modules\FaceSearch\Services\FaceSizeThresholdSweepService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class RunFaceSizeThresholdSweepCommand extends Command
{
    protected $signature = 'face-search:sweep-min-face-size
        {--dataset-root=%USERPROFILE%/Desktop/model/extracted/caltech_webfaces : Absolute dataset root for WebFaces images}
        {--ground-truth=%USERPROFILE%/Desktop/model/WebFaces_GroundThruth.txt : Absolute ground truth path}
        {--thresholds=16,24,32,40,48,64 : Comma-separated min_face_size_px values}
        {--limit=25 : Number of images to probe}
        {--selection=smallest_annotated_faces : smallest_annotated_faces|multi_face_density|sequential}
        {--provider=compreface : Detection provider key for the sweep}
        {--report-dir= : Optional absolute report directory override}';

    protected $description = 'Run a real min_face_size_px sweep against a local small-face dataset.';

    public function handle(FaceSizeThresholdSweepService $service): int
    {
        try {
            $report = $service->run(
                datasetRoot: (string) $this->option('dataset-root'),
                groundTruthPath: (string) $this->option('ground-truth'),
                thresholds: array_values(array_filter(array_map('trim', explode(',', (string) $this->option('thresholds'))))),
                limit: (int) $this->option('limit'),
                selection: (string) $this->option('selection'),
                providerKey: (string) $this->option('provider'),
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
        $directory = (string) ($this->option('report-dir') ?: storage_path('app/face-search-min-face-size-sweep'));

        File::ensureDirectoryExists($directory);

        $filename = sprintf('%s-face-search-min-face-size-sweep.json', now()->format('Ymd-His'));
        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }
}
