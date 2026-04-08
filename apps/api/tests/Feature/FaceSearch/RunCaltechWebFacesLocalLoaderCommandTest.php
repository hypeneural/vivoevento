<?php

use App\Modules\FaceSearch\Console\RunCaltechWebFacesLocalLoaderCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

it('runs the local Caltech WebFaces loader and prints the exporter report', function () {
    $outputDirectory = storage_path('app/testing/face-search-caltech-webfaces-loader');
    $datasetRoot = storage_path('app/testing/face-search-caltech-webfaces-dataset');
    $groundTruthPath = storage_path('app/testing/face-search-caltech-webfaces-ground-truth.txt');
    $manifestPath = $outputDirectory . DIRECTORY_SEPARATOR . 'manifest.json';

    File::deleteDirectory($outputDirectory);
    File::deleteDirectory($datasetRoot);
    File::delete($groundTruthPath);
    File::ensureDirectoryExists($outputDirectory);
    File::ensureDirectoryExists($datasetRoot);
    File::put($groundTruthPath, 'sample.jpg 10 10 30 10 20 20 20 30');
    File::put($manifestPath, json_encode([
        'status' => 'local_export_ready',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    Process::fake([
        '*' => Process::result(json_encode([
            'dataset' => 'caltech_webfaces',
            'manifest_path' => $manifestPath,
            'summary' => [
                'entries_exported' => 20,
            ],
            'request_outcome' => 'success',
        ], JSON_UNESCAPED_SLASHES), '', 0),
    ]);

    $this->artisan(RunCaltechWebFacesLocalLoaderCommand::class, [
        '--dataset-root' => $datasetRoot,
        '--ground-truth' => $groundTruthPath,
        '--selection' => 'sequential',
        '--limit' => 20,
        '--output-dir' => $outputDirectory,
    ])->assertSuccessful();

    Process::assertRanTimes(
        fn ($process, $result): bool => $process->path === base_path() && $result->successful(),
        1,
    );
});

it('fails clearly when the local Caltech WebFaces loader returns an invalid manifest payload', function () {
    $datasetRoot = storage_path('app/testing/invalid-caltech-webfaces-dataset');
    $groundTruthPath = storage_path('app/testing/invalid-caltech-webfaces-ground-truth.txt');

    File::deleteDirectory($datasetRoot);
    File::delete($groundTruthPath);
    File::ensureDirectoryExists($datasetRoot);
    File::put($groundTruthPath, 'sample.jpg 10 10 30 10 20 20 20 30');

    Process::fake([
        '*' => Process::result('{"dataset":"caltech_webfaces"}', '', 0),
    ]);

    $this->artisan(RunCaltechWebFacesLocalLoaderCommand::class, [
        '--dataset-root' => $datasetRoot,
        '--ground-truth' => $groundTruthPath,
        '--output-dir' => storage_path('app/testing/invalid-caltech-webfaces-loader'),
    ])->assertFailed();
});
