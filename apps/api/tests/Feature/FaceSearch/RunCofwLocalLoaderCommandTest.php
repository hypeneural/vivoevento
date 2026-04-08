<?php

use App\Modules\FaceSearch\Console\RunCofwLocalLoaderCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

it('runs the local COFW loader and prints the exporter report', function () {
    $outputDirectory = storage_path('app/testing/face-search-cofw-loader');
    $rootDirectory = storage_path('app/testing/face-search-cofw-root');
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
            'dataset' => 'cofw',
            'variant' => 'color',
            'manifest_path' => $manifestPath,
            'summary' => [
                'entries_exported' => 1007,
            ],
            'request_outcome' => 'success',
        ], JSON_UNESCAPED_SLASHES), '', 0),
    ]);

    $this->artisan(RunCofwLocalLoaderCommand::class, [
        '--variant' => 'color',
        '--root' => $rootDirectory,
        '--splits' => 'train,test',
        '--output-dir' => $outputDirectory,
    ])->assertSuccessful();

    Process::assertRanTimes(
        fn ($process, $result): bool => $process->path === base_path() && $result->successful(),
        1,
    );
});

it('fails clearly when the local COFW loader root is invalid', function () {
    $this->artisan(RunCofwLocalLoaderCommand::class, [
        '--root' => storage_path('app/testing/missing-face-search-cofw-root'),
    ])->assertFailed();
});
