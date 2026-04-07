<?php

use App\Modules\FaceSearch\Console\RunFaceIndexLaneThroughputCommand;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceBoundingBoxData;
use App\Modules\FaceSearch\DTOs\FaceEmbeddingData;
use App\Modules\FaceSearch\DTOs\FaceSearchMatchData;
use App\Modules\FaceSearch\Jobs\IndexMediaFacesJob;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\FaceSearch\Services\FaceDetectionProviderInterface;
use App\Modules\FaceSearch\Services\FaceEmbeddingProviderInterface;
use App\Modules\FaceSearch\Services\FaceIndexLaneExecutorInterface;
use App\Modules\FaceSearch\Services\FaceVectorStoreInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

it('measures the face-index lane using the real job pipeline and stores a throughput report', function () {
    Storage::fake('local');
    Storage::fake('ai-private');

    $fixtureRoot = base_path('tests/Fixtures/AI/local/test-throughput-dataset');
    $reportDir = storage_path('app/testing/face-search-lane-throughput');

    File::deleteDirectory($fixtureRoot);
    File::deleteDirectory($reportDir);
    File::ensureDirectoryExists($fixtureRoot);

    foreach (['single-a.jpg', 'group-b.jpg'] as $name) {
        $file = UploadedFile::fake()->image($name, 64, 64)->size(120);
        copy($file->getPathname(), $fixtureRoot . DIRECTORY_SEPARATOR . $name);
    }

    $manifestPath = writeFaceIndexThroughputManifest($fixtureRoot, [
        [
            'id' => 'single-a',
            'relative_path' => 'single-a.jpg',
            'event_id' => 'local-event',
            'person_id' => 'person-a',
            'expected_positive_set' => ['single-a'],
            'scene_type' => 'single_prominent',
            'quality_label' => 'good',
        ],
        [
            'id' => 'group-b',
            'relative_path' => 'group-b.jpg',
            'event_id' => 'local-event',
            'person_id' => 'person-b',
            'expected_positive_set' => ['group-b'],
            'scene_type' => 'group_two',
            'quality_label' => 'mixed',
        ],
    ]);

    app()->instance(FaceIndexLaneExecutorInterface::class, new class implements FaceIndexLaneExecutorInterface
    {
        public function execute(array $eventMediaIds, string $queueName = 'face-index'): array
        {
            foreach ($eventMediaIds as $eventMediaId) {
                (new IndexMediaFacesJob($eventMediaId))->handle();
            }

            return [
                'mode' => 'inline_test',
                'queue_name' => $queueName,
                'exit_code' => 0,
                'worker_output' => '',
                'wall_clock_ms' => 150,
            ];
        }
    });

    app()->instance(FaceDetectionProviderInterface::class, new class implements FaceDetectionProviderInterface
    {
        public function detect(
            \App\Modules\MediaProcessing\Models\EventMedia $media,
            \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings,
            string $binary,
        ): array {
            if (str_contains((string) $media->original_filename, 'group-b')) {
                return [
                    new DetectedFaceData(
                        boundingBox: new FaceBoundingBoxData(10, 10, 60, 60),
                        detectionConfidence: 0.95,
                        qualityScore: 0.70,
                        faceAreaRatio: 0.20,
                        isPrimaryCandidate: true,
                        providerEmbedding: [0.8, 0.2, 0.0],
                    ),
                    new DetectedFaceData(
                        boundingBox: new FaceBoundingBoxData(100, 10, 70, 70),
                        detectionConfidence: 0.98,
                        qualityScore: 0.85,
                        faceAreaRatio: 0.25,
                        isPrimaryCandidate: false,
                        providerEmbedding: [0.0, 1.0, 0.0],
                    ),
                ];
            }

            return [
                new DetectedFaceData(
                    boundingBox: new FaceBoundingBoxData(10, 10, 80, 80),
                    detectionConfidence: 0.99,
                    qualityScore: 0.90,
                    faceAreaRatio: 0.30,
                    isPrimaryCandidate: true,
                    providerEmbedding: [1.0, 0.0, 0.0],
                ),
            ];
        }
    });

    app()->instance(FaceEmbeddingProviderInterface::class, new class implements FaceEmbeddingProviderInterface
    {
        public function embed(
            \App\Modules\MediaProcessing\Models\EventMedia $media,
            \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings,
            string $cropBinary,
            DetectedFaceData $face,
        ): FaceEmbeddingData {
            return new FaceEmbeddingData(
                vector: $face->providerEmbedding !== [] ? $face->providerEmbedding : [0.1, 0.1, 0.1],
                providerKey: 'test',
                providerVersion: 'test-v1',
                modelKey: 'test-embedder',
                embeddingVersion: 'test-v1',
            );
        }
    });

    app()->instance(FaceVectorStoreInterface::class, new class implements FaceVectorStoreInterface
    {
        public function upsert(EventMediaFace $face, FaceEmbeddingData $embedding): EventMediaFace
        {
            return $face;
        }

        public function delete(EventMediaFace $face): void {}

        public function search(
            int $eventId,
            array $queryEmbedding,
            int $topK,
            ?float $threshold = null,
            bool $searchableOnly = true,
            ?string $searchStrategy = null,
        ): array {
            return [];
        }
    });

    $this->artisan(RunFaceIndexLaneThroughputCommand::class, [
        '--manifest' => $manifestPath,
        '--report-dir' => $reportDir,
    ])->assertSuccessful();

    $reports = File::files($reportDir);

    expect($reports)->toHaveCount(1);

    $report = json_decode((string) File::get($reports[0]->getPathname()), true, 512, JSON_THROW_ON_ERROR);

    expect($report['request_outcome'])->toBe('success')
        ->and($report['batch_size'])->toBe(2)
        ->and($report['summary']['jobs_completed'])->toBe(2)
        ->and((float) $report['summary']['throughput_face_index_per_minute'])->toBeGreaterThan(0)
        ->and($report['executor']['mode'])->toBe('inline_test')
        ->and($report['queue_name'])->toStartWith('face-index-benchmark-')
        ->and($report['scene_type_breakdown'])->toHaveCount(2)
        ->and(collect($report['scene_type_breakdown'])->pluck('scene_type')->all())->toContain('single_prominent', 'group_two')
        ->and(collect($report['scene_type_breakdown'])->firstWhere('scene_type', 'group_two'))->toHaveKeys([
            'avg_ms_per_detected_face',
            'avg_ms_per_indexed_face',
        ])
        ->and($report['slowest_items'][0])->toHaveKeys([
            'ms_per_detected_face',
            'ms_per_indexed_face',
        ]);
});

it('fails clearly when the face-index lane throughput command receives an invalid manifest', function () {
    $this->artisan(RunFaceIndexLaneThroughputCommand::class, [
        '--manifest' => 'tests/Fixtures/AI/local/missing-throughput-manifest.json',
    ])->assertFailed();
});

/**
 * @param array<int, array<string, mixed>> $entries
 */
function writeFaceIndexThroughputManifest(string $fixtureRoot, array $entries): string
{
    $manifestPath = storage_path('app/testing/face-search-throughput/' . uniqid('manifest-', true) . '.json');

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
        'entries' => array_map(static function (array $entry) use ($fixtureRoot): array {
            $path = $fixtureRoot . DIRECTORY_SEPARATOR . $entry['relative_path'];

            return [
                ...$entry,
                'is_public_search_eligible' => true,
                'expected_moderation_bucket' => 'safe',
                'consent_basis' => 'explicit_local_validation',
                'size_bytes' => filesize($path),
                'requires_derivative_for_compreface' => false,
                'target_face_selection' => [
                    'strategy' => 'largest',
                    'value' => 1,
                ],
            ];
        }, $entries),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return $manifestPath;
}
