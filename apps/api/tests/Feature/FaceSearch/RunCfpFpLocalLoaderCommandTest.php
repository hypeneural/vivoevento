<?php

use App\Modules\FaceSearch\Console\RunCfpFpLocalLoaderCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

it('runs the local CFP-FP loader and prints the exporter report', function () {
    $outputDirectory = storage_path('app/testing/face-search-cfp-fp-loader');
    $rootDirectory = storage_path('app/testing/face-search-cfp-fp-root');
    $manifestPath = $outputDirectory . DIRECTORY_SEPARATOR . 'manifest.json';

    File::deleteDirectory($outputDirectory);
    File::deleteDirectory($rootDirectory);
    File::ensureDirectoryExists($outputDirectory);
    File::ensureDirectoryExists($rootDirectory);
    File::put($manifestPath, json_encode([
        'status' => 'local_export_ready',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    Process::fake([
        '*' => Process::result(json_encode([
            'dataset' => 'cfp_fp',
            'manifest_path' => $manifestPath,
            'summary' => [
                'entries_exported' => 24,
                'people_selected' => 6,
            ],
            'request_outcome' => 'success',
        ], JSON_UNESCAPED_SLASHES), '', 0),
    ]);

    $this->artisan(RunCfpFpLocalLoaderCommand::class, [
        '--root' => $rootDirectory,
        '--people' => 6,
        '--frontal-per-person' => 2,
        '--profile-per-person' => 2,
        '--output-dir' => $outputDirectory,
    ])->assertSuccessful();

    Process::assertRanTimes(
        fn ($process, $result): bool => $process->path === base_path() && $result->successful(),
        1,
    );
});

it('fails clearly when the local CFP-FP loader root is invalid', function () {
    $this->artisan(RunCfpFpLocalLoaderCommand::class, [
        '--root' => storage_path('app/testing/missing-face-search-cfp-fp-root'),
    ])->assertFailed();
});
