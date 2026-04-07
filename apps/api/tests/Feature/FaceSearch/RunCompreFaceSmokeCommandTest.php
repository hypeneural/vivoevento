<?php

use App\Modules\FaceSearch\Services\CompreFaceClient;
use App\Modules\FaceSearch\Services\CompreFaceSmokeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;

it('runs compreface smoke in dry-run mode using the manifest contract without calling the provider', function () {
    $fixtureRoot = base_path('tests/Fixtures/AI/local/test-dataset');
    $reportDir = storage_path('app/testing/face-search-smoke/dry-run');

    File::deleteDirectory($fixtureRoot);
    File::deleteDirectory($reportDir);
    File::ensureDirectoryExists($fixtureRoot);

    $file = UploadedFile::fake()->image('person-a-1.jpg', 64, 64)->size(120);
    copy($file->getPathname(), $fixtureRoot . DIRECTORY_SEPARATOR . 'person-a-1.jpg');

    $manifestPath = writeSmokeManifest($fixtureRoot, [
        [
            'id' => 'person-a-1',
            'relative_path' => 'person-a-1.jpg',
            'event_id' => 'local-event',
            'person_id' => 'person-a',
            'expected_positive_set' => ['person-a-1'],
            'quality_label' => 'good',
            'is_public_search_eligible' => true,
            'expected_moderation_bucket' => 'safe',
            'consent_basis' => 'explicit_local_validation',
            'size_bytes' => filesize($fixtureRoot . DIRECTORY_SEPARATOR . 'person-a-1.jpg'),
            'requires_derivative_for_compreface' => false,
        ],
    ]);

    $this->mock(CompreFaceClient::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('detectBase64');
        $mock->shouldNotReceive('verifyEmbeddings');
    });

    $this->artisan('face-search:smoke-compreface', [
        '--manifest' => $manifestPath,
        '--dry-run' => true,
        '--report-dir' => $reportDir,
    ])->assertSuccessful();

    $reports = File::files($reportDir);

    expect($reports)->toHaveCount(1);

    $report = json_decode((string) File::get($reports[0]->getPathname()), true, 512, JSON_THROW_ON_ERROR);

    expect($report['dry_run'])->toBeTrue()
        ->and($report['request_outcome'])->toBe('success')
        ->and($report['entries'][0]['selected_relative_path'])->toBe('person-a-1.jpg');
});

it('runs compreface smoke against a manifest and stores a verification report', function () {
    $fixtureRoot = base_path('tests/Fixtures/AI/local/test-dataset-real');
    $reportDir = storage_path('app/testing/face-search-smoke/real-run');

    File::deleteDirectory($fixtureRoot);
    File::deleteDirectory($reportDir);
    File::ensureDirectoryExists($fixtureRoot);

    foreach (['person-a-1.jpg', 'person-a-2.jpg', 'person-b-1.jpg'] as $name) {
        $file = UploadedFile::fake()->image($name, 64, 64)->size(120);
        copy($file->getPathname(), $fixtureRoot . DIRECTORY_SEPARATOR . $name);
    }

    $manifestPath = writeSmokeManifest($fixtureRoot, [
        [
            'id' => 'person-a-1',
            'relative_path' => 'person-a-1.jpg',
            'event_id' => 'local-event',
            'person_id' => 'person-a',
            'expected_positive_set' => ['person-a-1', 'person-a-2'],
            'quality_label' => 'good',
            'is_public_search_eligible' => true,
            'expected_moderation_bucket' => 'safe',
            'consent_basis' => 'explicit_local_validation',
            'size_bytes' => filesize($fixtureRoot . DIRECTORY_SEPARATOR . 'person-a-1.jpg'),
            'requires_derivative_for_compreface' => false,
        ],
        [
            'id' => 'person-a-2',
            'relative_path' => 'person-a-2.jpg',
            'event_id' => 'local-event',
            'person_id' => 'person-a',
            'expected_positive_set' => ['person-a-1', 'person-a-2'],
            'quality_label' => 'good',
            'is_public_search_eligible' => true,
            'expected_moderation_bucket' => 'safe',
            'consent_basis' => 'explicit_local_validation',
            'size_bytes' => filesize($fixtureRoot . DIRECTORY_SEPARATOR . 'person-a-2.jpg'),
            'requires_derivative_for_compreface' => false,
            'target_face_selection' => [
                'strategy' => 'left_to_right_index',
                'value' => 2,
            ],
        ],
        [
            'id' => 'person-b-1',
            'relative_path' => 'person-b-1.jpg',
            'event_id' => 'local-event',
            'person_id' => 'person-b',
            'expected_positive_set' => ['person-b-1'],
            'quality_label' => 'good',
            'is_public_search_eligible' => true,
            'expected_moderation_bucket' => 'safe',
            'consent_basis' => 'explicit_local_validation',
            'size_bytes' => filesize($fixtureRoot . DIRECTORY_SEPARATOR . 'person-b-1.jpg'),
            'requires_derivative_for_compreface' => false,
        ],
    ]);

    $detections = [
        'person-a-1' => [
            'result' => [[
                'embedding' => [0.1, 0.2, 0.3],
            ]],
            'plugins_versions' => ['calculator' => 'facenet.Calculator'],
        ],
        'person-a-2' => [
            'result' => [
                [
                    'box' => ['x_min' => 10, 'y_min' => 10, 'x_max' => 60, 'y_max' => 60, 'probability' => 0.99],
                    'embedding' => [9.0, 9.0, 9.0],
                ],
                [
                    'box' => ['x_min' => 110, 'y_min' => 12, 'x_max' => 170, 'y_max' => 72, 'probability' => 0.98],
                    'embedding' => [0.1, 0.2, 0.29],
                ],
            ],
            'plugins_versions' => ['calculator' => 'facenet.Calculator'],
        ],
        'person-b-1' => [
            'result' => [[
                'embedding' => [0.9, 0.8, 0.7],
            ]],
            'plugins_versions' => ['calculator' => 'facenet.Calculator'],
        ],
    ];

    $this->mock(CompreFaceClient::class, function (MockInterface $mock) use ($fixtureRoot, $detections): void {
        $orderedIds = ['person-a-1', 'person-a-2', 'person-b-1'];

        foreach ($orderedIds as $id) {
            $expectedBase64 = base64_encode((string) File::get($fixtureRoot . DIRECTORY_SEPARATOR . "{$id}.jpg"));

            $mock->shouldReceive('detectBase64')
                ->once()
                ->withArgs(function (string $base64, array $options) use ($expectedBase64): bool {
                    return $base64 === $expectedBase64
                        && ($options['status'] ?? null) === true
                        && ($options['limit'] ?? null) === 0
                        && ($options['face_plugins'] ?? null) === 'calculator,landmarks';
                })
                ->andReturn($detections[$id]);
        }

        $mock->shouldReceive('verifyEmbeddings')
            ->once()
            ->andReturn(['result' => [['similarity' => 0.97]]]);

        $mock->shouldReceive('verifyEmbeddings')
            ->once()
            ->andReturn(['result' => [['similarity' => 0.14]]]);
    });

    $this->artisan('face-search:smoke-compreface', [
        '--manifest' => $manifestPath,
        '--report-dir' => $reportDir,
    ])->assertSuccessful();

    $reports = File::files($reportDir);

    expect($reports)->toHaveCount(1);

    $report = json_decode((string) File::get($reports[0]->getPathname()), true, 512, JSON_THROW_ON_ERROR);

    expect($report['dry_run'])->toBeFalse()
        ->and($report['request_outcome'])->toBe('success')
        ->and($report['detections'])->toHaveCount(3)
        ->and($report['detections'][1]['detected_faces_count'])->toBe(2)
        ->and($report['detections'][1]['selected_face_strategy'])->toBe('left_to_right_index')
        ->and($report['detections'][1]['selected_face_bbox']['x_min'])->toBe(110)
        ->and($report['verification_checks'])->toHaveCount(2)
        ->and($report['verification_checks'][0]['label'])->toBe('positive')
        ->and($report['verification_checks'][0]['similarity'])->toBe(0.97)
        ->and($report['verification_checks'][1]['label'])->toBe('negative')
        ->and($report['verification_checks'][1]['similarity'])->toBe(0.14);
});

it('fails cleanly when the compreface smoke service cannot run', function () {
    $this->mock(CompreFaceSmokeService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('run')
            ->once()
            ->andThrow(new RuntimeException('CompreFace smoke precondition failed.'));
    });

    $this->artisan('face-search:smoke-compreface', [
        '--manifest' => 'tests/Fixtures/AI/local/vipsocial.manifest.json',
    ])->assertFailed();
});

/**
 * @param array<int, array<string, mixed>> $entries
 */
function writeSmokeManifest(string $fixtureRoot, array $entries): string
{
    $manifestPath = storage_path('app/testing/face-search-smoke/' . uniqid('manifest-', true) . '.json');

    File::ensureDirectoryExists(dirname($manifestPath));
    File::put($manifestPath, json_encode([
        'version' => 1,
        'status' => 'local_consented_assets_ready',
        'asset_root_env' => '',
        'fallback_asset_root' => $fixtureRoot,
        'provider_constraints' => [
            'compreface_max_file_size_bytes' => 5 * 1024 * 1024,
        ],
        'privacy' => [
            'requires_explicit_consent' => true,
        ],
        'entries' => $entries,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return $manifestPath;
}
