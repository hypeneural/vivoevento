<?php

namespace App\Modules\FaceSearch\Console;

use App\Modules\FaceSearch\Services\CofwLocalLoaderService;
use Illuminate\Console\Command;
use Throwable;

class RunCofwLocalLoaderCommand extends Command
{
    protected $signature = 'face-search:load-cofw-local
        {--variant=color : color|gray}
        {--root= : Optional absolute COFW root override}
        {--splits=train,test : Comma-separated splits: train,test,all}
        {--include-lfpw : Include the first 845 LFPW images from the training split}
        {--limit=0 : Optional max number of exported images after filtering}
        {--output-dir= : Optional final export directory override}
        {--python-binary=python : Python executable used to run the COFW exporter}';

    protected $description = 'Export the local COFW dataset into image files plus a reusable detection manifest.';

    public function handle(CofwLocalLoaderService $service): int
    {
        try {
            $report = $service->run(
                variant: (string) $this->option('variant'),
                root: (string) $this->option('root'),
                splits: array_values(array_filter(array_map('trim', explode(',', (string) $this->option('splits'))))),
                includeLfpw: (bool) $this->option('include-lfpw'),
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
        $this->info(sprintf('COFW manifest saved to %s', (string) ($report['manifest_path'] ?? 'unknown')));

        return self::SUCCESS;
    }
}
