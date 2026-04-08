<?php

namespace App\Modules\FaceSearch\Console;

use App\Modules\FaceSearch\Services\OrganizeLocalGalleryByFaceService;
use Illuminate\Console\Command;
use Throwable;

class RunOrganizeLocalGalleryByFaceCommand extends Command
{
    protected $signature = 'face-search:organize-local-gallery
        {--input-dir= : Absolute input directory with event photos}
        {--output-dir= : Optional output directory for grouped people folders}
        {--no-face-dir= : Optional directory where images with no accepted faces stay recorded}
        {--provider=compreface : Detection and embedding provider key}
        {--cluster-threshold=0.35 : Max cosine distance to attach a face to an existing person cluster}
        {--min-face-size=24 : Local min_face_size_px override for this run}
        {--min-quality-score=0.6 : Local min_quality_score override for this run}
        {--max-dimension=2560 : Resize larger images before detection to reduce detector saturation}
        {--max-working-bytes=5242880 : Target max bytes for the working detection binary}
        {--limit=0 : Optional max number of images to process}
        {--extensions=jpg,jpeg,png,webp : Comma-separated allowed image extensions}';

    protected $description = 'Detect faces in a local event gallery, cluster repeated people, and copy each image into pessoa-### folders for manual validation.';

    public function handle(OrganizeLocalGalleryByFaceService $service): int
    {
        try {
            $report = $service->run(
                inputDirectory: (string) $this->option('input-dir'),
                outputDirectory: (string) $this->option('output-dir'),
                noFaceDirectory: (string) $this->option('no-face-dir'),
                providerKey: (string) $this->option('provider'),
                clusterThreshold: (float) $this->option('cluster-threshold'),
                minFaceSizePx: (int) $this->option('min-face-size'),
                minQualityScore: (float) $this->option('min-quality-score'),
                maxDimension: (int) $this->option('max-dimension'),
                maxWorkingBytes: (int) $this->option('max-working-bytes'),
                limit: (int) $this->option('limit'),
                extensions: array_values(array_filter(array_map('trim', explode(',', (string) $this->option('extensions'))))),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->newLine();
        $this->info(sprintf('People folders created under %s', (string) ($report['output_dir'] ?? 'unknown')));

        return self::SUCCESS;
    }
}
