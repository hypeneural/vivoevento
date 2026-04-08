<?php

use App\Modules\FaceSearch\Console\RunSearchThresholdSweepCommand;
use Illuminate\Support\Facades\File;

it('generates a structured search-threshold sweep report from a smoke report', function () {
    $reportDir = storage_path('app/testing/face-search-threshold-sweep');
    $sourceDir = storage_path('app/testing/face-search-threshold-sweep-source');
    File::deleteDirectory($reportDir);
    File::deleteDirectory($sourceDir);

    $smokeReportPath = writeThresholdSweepSmokeReport($sourceDir, [
        [
            'id' => 'person-a-1',
            'event_id' => 'benchmark-event-1',
            'person_id' => 'person-a',
            'expected_positive_set' => ['person-a-1', 'person-a-2'],
            'embedding' => [1.0, 0.0, 0.0],
            'scene_type' => 'single_prominent',
            'quality_label' => 'good',
            'detected_faces_count' => 1,
            'latency_ms' => 410.0,
            'detector_latency_ms' => 120.0,
            'calculator_latency_ms' => 210.0,
        ],
        [
            'id' => 'person-a-2',
            'event_id' => 'benchmark-event-1',
            'person_id' => 'person-a',
            'expected_positive_set' => ['person-a-1', 'person-a-2'],
            'embedding' => [0.98, 0.02, 0.0],
            'scene_type' => 'group_two',
            'quality_label' => 'mixed',
            'detected_faces_count' => 2,
            'latency_ms' => 460.0,
            'detector_latency_ms' => 125.0,
            'calculator_latency_ms' => 205.0,
        ],
        [
            'id' => 'person-b-1',
            'event_id' => 'benchmark-event-1',
            'person_id' => 'person-b',
            'expected_positive_set' => ['person-b-1', 'person-b-2'],
            'embedding' => [0.0, 1.0, 0.0],
            'scene_type' => 'single_prominent',
            'quality_label' => 'good',
            'detected_faces_count' => 1,
            'latency_ms' => 430.0,
            'detector_latency_ms' => 118.0,
            'calculator_latency_ms' => 215.0,
        ],
        [
            'id' => 'person-b-2',
            'event_id' => 'benchmark-event-1',
            'person_id' => 'person-b',
            'expected_positive_set' => ['person-b-1', 'person-b-2'],
            'embedding' => [0.0, 0.99, 0.01],
            'scene_type' => 'conversation_group',
            'quality_label' => 'profile_extreme',
            'detected_faces_count' => 4,
            'latency_ms' => 520.0,
            'detector_latency_ms' => 122.0,
            'calculator_latency_ms' => 220.0,
        ],
    ]);

    $this->artisan(RunSearchThresholdSweepCommand::class, [
        '--smoke-report' => $smokeReportPath,
        '--strategies' => 'exact',
        '--thresholds' => '0.02,0.50',
        '--report-dir' => $reportDir,
    ])->assertSuccessful();

    $reports = File::files($reportDir);

    expect($reports)->toHaveCount(1);

    $report = json_decode((string) File::get($reports[0]->getPathname()), true, 512, JSON_THROW_ON_ERROR);

    expect($report['request_outcome'])->toBe('success')
        ->and($report['thresholds_tested'])->toBe([0.02, 0.5])
        ->and($report['metric_semantics']['app_threshold_kind'])->toBe('pgvector_cosine_distance_upper_bound')
        ->and($report['runs'])->toHaveCount(2)
        ->and($report['recommendations'])->toHaveCount(1)
        ->and($report['recommendations'][0]['search_strategy'])->toBe('exact')
        ->and((float) $report['recommendations'][0]['recommended_threshold'])->toBe(0.02);
});

it('fails clearly when the search-threshold sweep is executed without a smoke report', function () {
    $this->artisan(RunSearchThresholdSweepCommand::class)
        ->assertFailed();
});

it('prefers a usable threshold over a trivially strict zero-hit threshold', function () {
    $reportDir = storage_path('app/testing/face-search-threshold-sweep-net-top1');
    $sourceDir = storage_path('app/testing/face-search-threshold-sweep-net-top1-source');
    File::deleteDirectory($reportDir);
    File::deleteDirectory($sourceDir);

    $smokeReportPath = writeThresholdSweepSmokeReport($sourceDir, [
        [
            'id' => 'person-a-1',
            'event_id' => 'benchmark-event-1',
            'person_id' => 'person-a',
            'expected_positive_set' => ['person-a-1', 'person-a-2'],
            'embedding' => [1.0, 0.0, 0.0],
            'scene_type' => 'single_prominent',
            'quality_label' => 'good',
            'detected_faces_count' => 1,
            'latency_ms' => 410.0,
            'detector_latency_ms' => 120.0,
            'calculator_latency_ms' => 210.0,
        ],
        [
            'id' => 'person-a-2',
            'event_id' => 'benchmark-event-1',
            'person_id' => 'person-a',
            'expected_positive_set' => ['person-a-1', 'person-a-2'],
            'embedding' => [0.8, 0.6, 0.0],
            'scene_type' => 'single_prominent',
            'quality_label' => 'good',
            'detected_faces_count' => 1,
            'latency_ms' => 430.0,
            'detector_latency_ms' => 125.0,
            'calculator_latency_ms' => 205.0,
        ],
        [
            'id' => 'person-b-1',
            'event_id' => 'benchmark-event-1',
            'person_id' => 'person-b',
            'expected_positive_set' => ['person-b-1', 'person-b-2'],
            'embedding' => [0.0, 1.0, 0.0],
            'scene_type' => 'single_prominent',
            'quality_label' => 'good',
            'detected_faces_count' => 1,
            'latency_ms' => 420.0,
            'detector_latency_ms' => 118.0,
            'calculator_latency_ms' => 215.0,
        ],
        [
            'id' => 'person-b-2',
            'event_id' => 'benchmark-event-1',
            'person_id' => 'person-b',
            'expected_positive_set' => ['person-b-1', 'person-b-2'],
            'embedding' => [0.0, 0.8, 0.6],
            'scene_type' => 'single_prominent',
            'quality_label' => 'good',
            'detected_faces_count' => 1,
            'latency_ms' => 440.0,
            'detector_latency_ms' => 122.0,
            'calculator_latency_ms' => 220.0,
        ],
    ]);

    $this->artisan(RunSearchThresholdSweepCommand::class, [
        '--smoke-report' => $smokeReportPath,
        '--strategies' => 'exact',
        '--thresholds' => '0.05,0.25',
        '--report-dir' => $reportDir,
    ])->assertSuccessful();

    $reports = File::files($reportDir);

    expect($reports)->toHaveCount(1);

    $report = json_decode((string) File::get($reports[0]->getPathname()), true, 512, JSON_THROW_ON_ERROR);

    expect($report['recommendations'])->toHaveCount(1)
        ->and($report['recommendations'][0]['search_strategy'])->toBe('exact')
        ->and((float) $report['recommendations'][0]['recommended_threshold'])->toBe(0.25)
        ->and((float) $report['recommendations'][0]['net_top_1_score'])->toBe(1.0);
});

/**
 * @param array<int, array<string, mixed>> $detections
 */
function writeThresholdSweepSmokeReport(string $directory, array $detections): string
{
    $path = $directory . DIRECTORY_SEPARATOR . uniqid('smoke-report-', true) . '.json';
    File::ensureDirectoryExists(dirname($path));

    File::put($path, json_encode([
        'provider' => 'compreface',
        'threshold_tested' => 0.42,
        'entries' => array_map(static fn (array $detection): array => [
            'id' => $detection['id'],
        ], $detections),
        'detections' => $detections,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return $path;
}
