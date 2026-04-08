<?php

namespace App\Modules\FaceSearch\Console;

use App\Modules\FaceSearch\Services\CalfwLocalLoaderService;
use Illuminate\Console\Command;
use Throwable;

class RunCalfwLocalLoaderCommand extends Command
{
    protected $signature = 'face-search:load-calfw-local
        {--root= : Optional absolute CALFW root override}
        {--selection=largest_identities : largest_identities|sequential}
        {--image-selection=spread : spread|sequential}
        {--offset=0 : Skip this many ranked identities before exporting}
        {--people=12 : Number of identities to export}
        {--images-per-person=4 : Number of images exported for each identity}
        {--min-images-per-person=4 : Minimum images required to keep an identity}
        {--output-dir= : Optional final export directory override}
        {--python-binary=python : Python executable used to run the CALFW exporter}';

    protected $description = 'Export the local CALFW dataset into a reusable identity holdout manifest for FaceSearch.';

    public function handle(CalfwLocalLoaderService $service): int
    {
        try {
            $report = $service->run(
                root: (string) $this->option('root'),
                selection: (string) $this->option('selection'),
                imageSelection: (string) $this->option('image-selection'),
                offset: (int) $this->option('offset'),
                people: (int) $this->option('people'),
                imagesPerPerson: (int) $this->option('images-per-person'),
                minImagesPerPerson: (int) $this->option('min-images-per-person'),
                outputDirectory: (string) $this->option('output-dir'),
                pythonBinary: (string) $this->option('python-binary'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->newLine();
        $this->info(sprintf('CALFW manifest saved to %s', (string) ($report['manifest_path'] ?? 'unknown')));

        return self::SUCCESS;
    }
}
