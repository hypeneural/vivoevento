<?php

namespace App\Modules\FaceSearch\Console;

use App\Modules\FaceSearch\Services\DetectionDatasetProbeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class RunDetectionDatasetProbeCommand extends Command
{
    protected $signature = 'face-search:probe-detection-dataset
        {--manifest= : Detection dataset manifest path, absolute or relative to base_path()}
        {--limit=0 : Optional max number of images to probe}
        {--selection=sequential : sequential|highest_occlusion|smallest_face|dense_annotations}
        {--splits= : Optional comma-separated split filter}
        {--occlusion-buckets= : Optional comma-separated occlusion buckets: none|light|moderate|heavy|unknown}
        {--face-size-buckets= : Optional comma-separated face-size buckets: small_lt_32|medium_32_63|large_64_95|xlarge_gte_96|unknown}
        {--density-buckets= : Optional comma-separated density buckets: single|group_2_5|dense_6_10|crowd_11_plus}
        {--provider=compreface : Detection provider key}
        {--iou-threshold=0.20 : IoU threshold for annotation/detection matching}
        {--include-invalid-annotations : Keep annotations flagged as invalid in metrics}
        {--report-dir= : Optional absolute report directory override}';

    protected $description = 'Run a real detection probe against a dataset manifest exported for FaceSearch calibration.';

    public function handle(DetectionDatasetProbeService $service): int
    {
        try {
            $report = $service->run(
                manifestPath: (string) $this->option('manifest'),
                limit: (int) $this->option('limit'),
                selection: (string) $this->option('selection'),
                splits: array_values(array_filter(array_map('trim', explode(',', (string) $this->option('splits'))))),
                occlusionBuckets: array_values(array_filter(array_map('trim', explode(',', (string) $this->option('occlusion-buckets'))))),
                faceSizeBuckets: array_values(array_filter(array_map('trim', explode(',', (string) $this->option('face-size-buckets'))))),
                densityBuckets: array_values(array_filter(array_map('trim', explode(',', (string) $this->option('density-buckets'))))),
                providerKey: (string) $this->option('provider'),
                iouThreshold: (float) $this->option('iou-threshold'),
                includeInvalidAnnotations: (bool) $this->option('include-invalid-annotations'),
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
        $directory = (string) ($this->option('report-dir') ?: storage_path('app/face-search-detection-probe'));

        File::ensureDirectoryExists($directory);

        $filename = sprintf('%s-face-search-detection-probe.json', now()->format('Ymd-His'));
        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }
}
