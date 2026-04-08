<?php

use App\Modules\FaceSearch\Console\RunManifestFaceSizeThresholdSweepCommand;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceBoundingBoxData;
use App\Modules\FaceSearch\Services\FaceDetectionProviderInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

it('runs a manifest-based min face size sweep and stores threshold metrics', function () {
    $fixtureRoot = storage_path('app/testing/face-search-manifest-min-face-size-sweep');
    $reportDir = storage_path('app/testing/face-search-manifest-min-face-size-sweep-reports');

    File::deleteDirectory($fixtureRoot);
    File::deleteDirectory($reportDir);
    File::ensureDirectoryExists($fixtureRoot);

    foreach (['portrait.png', 'group.png'] as $name) {
        $file = UploadedFile::fake()->image($name, 256, 256)->size(120);
        copy($file->getPathname(), $fixtureRoot . DIRECTORY_SEPARATOR . $name);
    }

    $manifestPath = $fixtureRoot . DIRECTORY_SEPARATOR . 'manifest.json';
    File::put($manifestPath, json_encode([
        'dataset' => 'manifest-min-face-size-fixture',
        'lane' => 'detection',
        'entries' => [
            [
                'id' => 'portrait',
                'split' => 'test',
                'relative_path' => 'portrait.png',
                'scene_type' => 'portrait',
                'quality_label' => 'good',
                'bbox' => ['x' => 20, 'y' => 20, 'width' => 100, 'height' => 100],
                'face_span_min_px' => 100,
            ],
            [
                'id' => 'group',
                'split' => 'validation',
                'relative_path' => 'group.png',
                'scene_type' => 'group',
                'quality_label' => 'small_face',
                'annotations' => [
                    [
                        'bbox' => ['x' => 10, 'y' => 10, 'width' => 30, 'height' => 30],
                        'face_span_min_px' => 30,
                        'invalid' => false,
                    ],
                    [
                        'bbox' => ['x' => 80, 'y' => 80, 'width' => 35, 'height' => 35],
                        'face_span_min_px' => 35,
                        'invalid' => false,
                    ],
                ],
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    app()->instance(FaceDetectionProviderInterface::class, new class implements FaceDetectionProviderInterface
    {
        public function detect(
            \App\Modules\MediaProcessing\Models\EventMedia $media,
            \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings,
            string $binary,
        ): array {
            return match ((string) $media->original_filename) {
                'portrait.png' => [
                    new DetectedFaceData(
                        boundingBox: new FaceBoundingBoxData(22, 22, 98, 98),
                        detectionConfidence: 0.99,
                        qualityScore: 0.99,
                    ),
                ],
                default => [
                    new DetectedFaceData(
                        boundingBox: new FaceBoundingBoxData(12, 12, 28, 28),
                        detectionConfidence: 0.99,
                        qualityScore: 0.99,
                    ),
                    new DetectedFaceData(
                        boundingBox: new FaceBoundingBoxData(82, 82, 34, 34),
                        detectionConfidence: 0.99,
                        qualityScore: 0.99,
                    ),
                    new DetectedFaceData(
                        boundingBox: new FaceBoundingBoxData(150, 150, 20, 20),
                        detectionConfidence: 0.99,
                        qualityScore: 0.99,
                    ),
                ],
            };
        }
    });

    $this->artisan(RunManifestFaceSizeThresholdSweepCommand::class, [
        '--manifest' => $manifestPath,
        '--thresholds' => '24,32,40',
        '--report-dir' => $reportDir,
    ])->assertSuccessful();

    $reports = File::files($reportDir);

    expect($reports)->toHaveCount(1);

    $report = json_decode((string) File::get($reports[0]->getPathname()), true, 512, JSON_THROW_ON_ERROR);
    $breakdown = collect($report['threshold_breakdown'])->keyBy('threshold');

    expect($report['request_outcome'])->toBe('success')
        ->and($report['baseline_summary']['annotation_recall_estimated'])->toEqual(1.0)
        ->and($report['baseline_summary']['detection_precision_estimated'])->toEqual(0.75)
        ->and($breakdown->get(24)['annotation_recall_estimated'])->toEqual(1.0)
        ->and($breakdown->get(24)['detection_precision_estimated'])->toEqual(1.0)
        ->and($breakdown->get(32)['annotation_recall_estimated'])->toBe(0.6667)
        ->and($breakdown->get(32)['retained_detected_faces_total'])->toBe(2)
        ->and($breakdown->get(40)['annotation_recall_estimated'])->toBe(0.3333)
        ->and($report['recommended_threshold']['threshold'])->toBe(24);
});

it('fails clearly when the manifest-based min face size sweep receives an invalid manifest path', function () {
    $this->artisan(RunManifestFaceSizeThresholdSweepCommand::class, [
        '--manifest' => storage_path('app/testing/missing-face-search-manifest-min-face-size-sweep/manifest.json'),
    ])->assertFailed();
});
