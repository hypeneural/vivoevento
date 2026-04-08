<?php

use App\Modules\FaceSearch\Console\RunSmokeMinFaceSizeAnalysisCommand;
use Illuminate\Support\Facades\File;

it('analyzes min face size retention from a smoke report and stores a structured report', function () {
    $reportDir = storage_path('app/testing/face-search-smoke-min-face-size-analysis');
    $sourceDir = storage_path('app/testing/face-search-smoke-min-face-size-analysis-source');
    File::deleteDirectory($reportDir);
    File::deleteDirectory($sourceDir);

    $smokeReportPath = writeSmokeMinFaceSizeAnalysisReport($sourceDir, [
        [
            'id' => 'person-a-1',
            'person_id' => 'person-a',
            'scene_type' => 'single_prominent',
            'quality_label' => 'good',
            'request_outcome' => 'success',
            'detected_faces_count' => 1,
            'selected_face_bbox' => ['x_min' => 10, 'y_min' => 10, 'x_max' => 110, 'y_max' => 130],
        ],
        [
            'id' => 'person-b-1',
            'person_id' => 'person-b',
            'scene_type' => 'group_two',
            'quality_label' => 'mixed',
            'request_outcome' => 'success',
            'detected_faces_count' => 2,
            'selected_face_bbox' => ['x_min' => 0, 'y_min' => 0, 'x_max' => 30, 'y_max' => 35],
        ],
        [
            'id' => 'person-c-1',
            'person_id' => 'person-c',
            'scene_type' => 'conversation_group',
            'quality_label' => 'profile_extreme',
            'request_outcome' => 'failed',
            'detected_faces_count' => 0,
            'selected_face_bbox' => null,
        ],
    ]);

    $this->artisan(RunSmokeMinFaceSizeAnalysisCommand::class, [
        '--smoke-report' => $smokeReportPath,
        '--thresholds' => '24,32,96',
        '--report-dir' => $reportDir,
    ])->assertSuccessful();

    $reports = File::files($reportDir);

    expect($reports)->toHaveCount(1);

    $report = json_decode((string) File::get($reports[0]->getPathname()), true, 512, JSON_THROW_ON_ERROR);
    $breakdown = collect($report['threshold_breakdown'])->keyBy('threshold');

    expect($report['baseline_summary']['entries_count'])->toBe(3)
        ->and($report['baseline_summary']['entries_successful'])->toBe(2)
        ->and($report['baseline_summary']['selected_face_min_side_px_min'])->toEqual(30.0)
        ->and($breakdown->get(24)['retained_entries_total'])->toBe(2)
        ->and($breakdown->get(32)['retained_entries_total'])->toBe(1)
        ->and($breakdown->get(96)['retained_entries_total'])->toBe(1)
        ->and($report['recommended_threshold']['threshold'])->toBe(24);
});

it('fails clearly when the smoke min face size analysis receives an invalid smoke report path', function () {
    $this->artisan(RunSmokeMinFaceSizeAnalysisCommand::class, [
        '--smoke-report' => storage_path('app/testing/missing-face-search-smoke-min-face-size-analysis/report.json'),
    ])->assertFailed();
});

/**
 * @param array<int, array<string, mixed>> $detections
 */
function writeSmokeMinFaceSizeAnalysisReport(string $directory, array $detections): string
{
    $path = $directory . DIRECTORY_SEPARATOR . uniqid('smoke-report-', true) . '.json';
    File::ensureDirectoryExists(dirname($path));

    File::put($path, json_encode([
        'provider' => 'compreface',
        'manifest_path' => 'tests/Fixtures/AI/local/vipsocial.manifest.json',
        'request_outcome' => 'degraded',
        'detections' => $detections,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return $path;
}
