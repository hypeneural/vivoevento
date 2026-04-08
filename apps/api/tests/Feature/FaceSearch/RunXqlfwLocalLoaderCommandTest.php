<?php

use App\Modules\FaceSearch\Console\RunXqlfwLocalLoaderCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

it('runs the local XQLFW loader and prints the exporter report', function () {
    $outputDirectory = storage_path('app/testing/face-search-xqlfw-loader');
    $rootDirectory = storage_path('app/testing/face-search-xqlfw-root');
    $scoresPath = storage_path('app/testing/face-search-xqlfw-scores.txt');
    $pairsPath = storage_path('app/testing/face-search-xqlfw-pairs.txt');
    $manifestPath = $outputDirectory . DIRECTORY_SEPARATOR . 'manifest.json';

    File::deleteDirectory($outputDirectory);
    File::deleteDirectory($rootDirectory);
    File::ensureDirectoryExists($outputDirectory);
    File::ensureDirectoryExists($rootDirectory);
    File::put($manifestPath, json_encode([
        'status' => 'local_export_ready',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    File::put($scoresPath, "ID\tNum\tScore\nAaron_Peirsol\t1\t0.80\n");
    File::put($pairsPath, "10\t300\nAaron_Peirsol\t1\t2\n");

    Process::fake([
        '*' => Process::result(json_encode([
            'dataset' => 'xqlfw',
            'variant' => 'original',
            'manifest_path' => $manifestPath,
            'summary' => [
                'entries_exported' => 24,
                'people_selected' => 6,
            ],
            'request_outcome' => 'success',
        ], JSON_UNESCAPED_SLASHES), '', 0),
    ]);

    $this->artisan(RunXqlfwLocalLoaderCommand::class, [
        '--variant' => 'original',
        '--root' => $rootDirectory,
        '--scores-path' => $scoresPath,
        '--pairs-path' => $pairsPath,
        '--selection' => 'official_pairs',
        '--people' => 6,
        '--images-per-person' => 4,
        '--output-dir' => $outputDirectory,
    ])->assertSuccessful();

    Process::assertRanTimes(
        fn ($process, $result): bool => $process->path === base_path() && $result->successful(),
        1,
    );
});

it('fails clearly when the local XQLFW loader root is invalid', function () {
    $this->artisan(RunXqlfwLocalLoaderCommand::class, [
        '--root' => storage_path('app/testing/missing-face-search-xqlfw-root'),
    ])->assertFailed();
});
