<?php

namespace App\Modules\FaceSearch\Console;

use App\Modules\FaceSearch\Services\WiderFaceLocalLoaderService;
use Illuminate\Console\Command;
use Throwable;

class RunWiderFaceLocalLoaderCommand extends Command
{
    protected $signature = 'face-search:load-wider-face
        {--cache-dir=%USERPROFILE%/Desktop/model/tfds-wider-face : Cache directory for official WIDER FACE source archives}
        {--splits=validation : validation|train|test|all}
        {--selection=dense_annotations : dense_annotations|smallest_face|sequential}
        {--limit=50 : Number of images to export}
        {--output-dir= : Optional final export directory override}
        {--python-binary=python : Python executable used to run the WIDER FACE exporter}';

    protected $description = 'Export a local WIDER FACE slice into image files plus a reusable detection manifest.';

    public function handle(WiderFaceLocalLoaderService $service): int
    {
        try {
            $report = $service->run(
                cacheDirectory: (string) $this->option('cache-dir'),
                splits: array_values(array_filter(array_map('trim', explode(',', (string) $this->option('splits'))))),
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
        $this->info(sprintf('WIDER FACE manifest saved to %s', (string) ($report['manifest_path'] ?? 'unknown')));

        return self::SUCCESS;
    }
}
