<?php

use App\Modules\FaceSearch\Console\RunWiderFaceLocalLoaderCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

it('runs the local WIDER FACE loader and prints the exporter report', function () {
    $outputDirectory = storage_path('app/testing/face-search-wider-face-loader');
    $cacheDirectory = storage_path('app/testing/face-search-wider-face-cache');
    $manifestPath = $outputDirectory . DIRECTORY_SEPARATOR . 'manifest.json';

    File::deleteDirectory($outputDirectory);
    File::deleteDirectory($cacheDirectory);
    File::ensureDirectoryExists($outputDirectory);
    File::ensureDirectoryExists($cacheDirectory);
    File::put($manifestPath, json_encode([
        'status' => 'local_export_ready',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    Process::fake([
        '*' => Process::result(json_encode([
            'dataset' => 'wider_face',
            'manifest_path' => $manifestPath,
            'summary' => [
                'entries_exported' => 20,
            ],
            'request_outcome' => 'success',
        ], JSON_UNESCAPED_SLASHES), '', 0),
    ]);

    $this->artisan(RunWiderFaceLocalLoaderCommand::class, [
        '--cache-dir' => $cacheDirectory,
        '--splits' => 'validation',
        '--selection' => 'dense_annotations',
        '--limit' => 20,
        '--output-dir' => $outputDirectory,
    ])->assertSuccessful();

    Process::assertRanTimes(
        fn ($process, $result): bool => $process->path === base_path() && $result->successful(),
        1,
    );
});

it('fails clearly when the local WIDER FACE loader returns an invalid manifest payload', function () {
    Process::fake([
        '*' => Process::result('{"dataset":"wider_face"}', '', 0),
    ]);

    $this->artisan(RunWiderFaceLocalLoaderCommand::class, [
        '--output-dir' => storage_path('app/testing/invalid-wider-face-loader'),
    ])->assertFailed();
});
