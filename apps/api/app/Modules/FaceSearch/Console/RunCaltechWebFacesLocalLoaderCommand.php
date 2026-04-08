<?php

namespace App\Modules\FaceSearch\Console;

use App\Modules\FaceSearch\Services\CaltechWebFacesLocalLoaderService;
use Illuminate\Console\Command;
use Throwable;

class RunCaltechWebFacesLocalLoaderCommand extends Command
{
    protected $signature = 'face-search:load-caltech-webfaces-local
        {--dataset-root=%USERPROFILE%/Desktop/model/extracted/caltech_webfaces : Absolute dataset root for WebFaces images}
        {--ground-truth=%USERPROFILE%/Desktop/model/WebFaces_GroundThruth.txt : Absolute ground truth path}
        {--selection=sequential : sequential|smallest_annotated_faces|multi_face_density}
        {--limit=100 : Number of images to export}
        {--output-dir= : Optional final export directory override}
        {--python-binary=python : Python executable used to run the Caltech WebFaces exporter}';

    protected $description = 'Export the local Caltech WebFaces dataset into image files plus a reusable detection manifest.';

    public function handle(CaltechWebFacesLocalLoaderService $service): int
    {
        try {
            $report = $service->run(
                datasetRoot: (string) $this->option('dataset-root'),
                groundTruthPath: (string) $this->option('ground-truth'),
                selection: (string) $this->option('selection'),
                limit: (int) $this->option('limit'),
                outputDirectory: (string) $this->option('output-dir'),
                pythonBinary: (string) $this->option('python-binary'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->newLine();
        $this->info(sprintf('Caltech WebFaces manifest saved to %s', (string) ($report['manifest_path'] ?? 'unknown')));

        return self::SUCCESS;
    }
}
