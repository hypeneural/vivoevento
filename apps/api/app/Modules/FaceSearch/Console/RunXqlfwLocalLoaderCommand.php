<?php

namespace App\Modules\FaceSearch\Console;

use App\Modules\FaceSearch\Services\XqlfwLocalLoaderService;
use Illuminate\Console\Command;
use Throwable;

class RunXqlfwLocalLoaderCommand extends Command
{
    protected $signature = 'face-search:load-xqlfw-local
        {--variant=original : original|aligned_112}
        {--root= : Optional absolute XQLFW image root override}
        {--scores-path= : Optional absolute xqlfw_scores.txt override}
        {--pairs-path= : Optional absolute xqlfw_pairs.txt override}
        {--selection=official_pairs : official_pairs|highest_quality|sequential}
        {--image-selection=score_spread : score_spread|top_score|sequential}
        {--offset=0 : Skip this many ranked identities before exporting}
        {--people=12 : Number of identities to export}
        {--images-per-person=4 : Number of images exported for each identity}
        {--min-images-per-person=2 : Minimum images required to keep an identity}
        {--output-dir= : Optional final export directory override}
        {--python-binary=python : Python executable used to run the XQLFW exporter}';

    protected $description = 'Export the local XQLFW dataset into a reusable identity manifest for FaceSearch smoke and benchmark runs.';

    public function handle(XqlfwLocalLoaderService $service): int
    {
        try {
            $report = $service->run(
                variant: (string) $this->option('variant'),
                root: (string) $this->option('root'),
                scoresPath: (string) $this->option('scores-path'),
                pairsPath: (string) $this->option('pairs-path'),
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
        $this->info(sprintf('XQLFW manifest saved to %s', (string) ($report['manifest_path'] ?? 'unknown')));

        return self::SUCCESS;
    }
}
