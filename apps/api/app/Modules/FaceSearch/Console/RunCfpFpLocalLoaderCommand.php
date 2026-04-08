<?php

namespace App\Modules\FaceSearch\Console;

use App\Modules\FaceSearch\Services\CfpFpLocalLoaderService;
use Illuminate\Console\Command;
use Throwable;

class RunCfpFpLocalLoaderCommand extends Command
{
    protected $signature = 'face-search:load-cfp-fp-local
        {--root= : Optional absolute CFP-FP root override}
        {--image-selection=spread : spread|sequential}
        {--offset=0 : Skip this many ranked subjects before exporting}
        {--people=12 : Number of subjects to export}
        {--frontal-per-person=2 : Number of frontal images exported for each subject}
        {--profile-per-person=2 : Number of profile images exported for each subject}
        {--output-dir= : Optional final export directory override}
        {--python-binary=python : Python executable used to run the CFP-FP exporter}';

    protected $description = 'Export the local CFP-FP dataset into a reusable pose holdout manifest for FaceSearch.';

    public function handle(CfpFpLocalLoaderService $service): int
    {
        try {
            $report = $service->run(
                root: (string) $this->option('root'),
                imageSelection: (string) $this->option('image-selection'),
                offset: (int) $this->option('offset'),
                people: (int) $this->option('people'),
                frontalPerPerson: (int) $this->option('frontal-per-person'),
                profilePerPerson: (int) $this->option('profile-per-person'),
                outputDirectory: (string) $this->option('output-dir'),
                pythonBinary: (string) $this->option('python-binary'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->newLine();
        $this->info(sprintf('CFP-FP manifest saved to %s', (string) ($report['manifest_path'] ?? 'unknown')));

        return self::SUCCESS;
    }
}
