<?php

use App\Modules\FaceSearch\Console\RunLfwLocalLoaderCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

it('runs the local LFW loader and prints the exporter report', function () {
    $outputDirectory = storage_path('app/testing/face-search-lfw-loader');
    $rootDirectory = storage_path('app/testing/face-search-lfw-root');
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
            'dataset' => 'lfw',
            'manifest_path' => $manifestPath,
            'summary' => [
                'entries_exported' => 24,
                'people_selected' => 6,
            ],
            'request_outcome' => 'success',
        ], JSON_UNESCAPED_SLASHES), '', 0),
    ]);

    $this->artisan(RunLfwLocalLoaderCommand::class, [
        '--root' => $rootDirectory,
        '--selection' => 'largest_identities',
        '--people' => 6,
        '--images-per-person' => 4,
        '--output-dir' => $outputDirectory,
    ])->assertSuccessful();

    Process::assertRanTimes(
        fn ($process, $result): bool => $process->path === base_path() && $result->successful(),
        1,
    );
});

it('fails clearly when the local LFW loader root is invalid', function () {
    $this->artisan(RunLfwLocalLoaderCommand::class, [
        '--root' => storage_path('app/testing/missing-face-search-lfw-root'),
    ])->assertFailed();
});
