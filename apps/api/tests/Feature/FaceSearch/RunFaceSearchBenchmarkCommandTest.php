<?php

use Illuminate\Support\Facades\File;

it('fails clearly when face-search benchmark is executed without a smoke report', function () {
    $this->artisan('face-search:benchmark')
        ->assertFailed();
});

it('generates a structured benchmark report from a smoke report', function () {
    $reportDir = storage_path('app/testing/face-search-benchmark');
    $sourceDir = storage_path('app/testing/face-search-benchmark-source');
    File::deleteDirectory($reportDir);
    File::deleteDirectory($sourceDir);

    $smokeReportPath = writeBenchmarkSmokeReport($sourceDir, [
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

    $this->artisan('face-search:benchmark', [
        '--smoke-report' => $smokeReportPath,
        '--report-dir' => $reportDir,
    ])->assertSuccessful();

    $reports = File::files($reportDir);

    expect($reports)->toHaveCount(1);

    $report = json_decode((string) File::get($reports[0]->getPathname()), true, 512, JSON_THROW_ON_ERROR);

    expect($report['entries_count'])->toBe(4)
        ->and($report['request_outcome'])->toBe('success')
        ->and($report['dataset_summary']['unique_people'])->toBe(2)
        ->and($report['dataset_summary']['multi_face_entries'])->toBe(2)
        ->and((float) $report['dataset_summary']['source_smoke_threshold_tested'])->toBe(0.42)
        ->and($report['strategies'])->toHaveCount(2)
        ->and($report['strategies'][0]['search_strategy'])->toBe('exact')
        ->and($report['strategies'][0]['queries_evaluated'])->toBe(4)
        ->and((float) $report['strategies'][0]['top_1_hit_rate'])->toBe(1.0)
        ->and((float) $report['strategies'][0]['top_5_hit_rate'])->toBe(1.0)
        ->and((float) $report['operational_summary']['p95_detect_ms'])->toBe(125.0)
        ->and((float) $report['operational_summary']['p95_embed_ms'])->toBe(220.0)
        ->and((float) $report['operational_summary']['throughput_face_index_per_minute'])->toBeGreaterThan(0)
        ->and($report['operational_summary']['slowest_detection_ids'][0]['id'])->toBe('person-b-2');

    $exactStrategy = collect($report['strategies'])->firstWhere('search_strategy', 'exact');
    $exactSceneBreakdown = collect($exactStrategy['scene_type_breakdown'])->keyBy('scene_type');
    $exactFaceCountBreakdown = collect($exactStrategy['detected_faces_count_breakdown'])->keyBy('detected_faces_count');
    $datasetFaceCountBreakdown = collect($report['dataset_summary']['detected_faces_count_counts'])->keyBy('detected_faces_count');

    expect($exactSceneBreakdown->get('group_two')['queries_evaluated'])->toBe(1)
        ->and((float) $exactSceneBreakdown->get('group_two')['top_1_hit_rate'])->toBe(1.0)
        ->and($exactFaceCountBreakdown->get(4)['queries_evaluated'])->toBe(1)
        ->and($datasetFaceCountBreakdown->get(1)['count'])->toBe(2)
        ->and($datasetFaceCountBreakdown->get(2)['count'])->toBe(1)
        ->and($datasetFaceCountBreakdown->get(4)['count'])->toBe(1);
});

/**
 * @param array<int, array<string, mixed>> $detections
 */
function writeBenchmarkSmokeReport(string $directory, array $detections): string
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
