<?php

use App\Modules\FaceSearch\Console\RunCalfwLocalLoaderCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

it('runs the local CALFW loader and prints the exporter report', function () {
    $outputDirectory = storage_path('app/testing/face-search-calfw-loader');
    $rootDirectory = storage_path('app/testing/face-search-calfw-root');
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
            'dataset' => 'calfw',
            'manifest_path' => $manifestPath,
            'summary' => [
                'entries_exported' => 24,
                'people_selected' => 6,
            ],
            'request_outcome' => 'success',
        ], JSON_UNESCAPED_SLASHES), '', 0),
    ]);

    $this->artisan(RunCalfwLocalLoaderCommand::class, [
        '--root' => $rootDirectory,
        '--people' => 6,
        '--images-per-person' => 4,
        '--output-dir' => $outputDirectory,
    ])->assertSuccessful();

    Process::assertRanTimes(
        fn ($process, $result): bool => $process->path === base_path() && $result->successful(),
        1,
    );
});

it('fails clearly when the local CALFW loader root is invalid', function () {
    $this->artisan(RunCalfwLocalLoaderCommand::class, [
        '--root' => storage_path('app/testing/missing-face-search-calfw-root'),
    ])->assertFailed();
});
