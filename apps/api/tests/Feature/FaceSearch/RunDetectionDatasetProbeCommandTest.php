<?php

use App\Modules\FaceSearch\Console\RunDetectionDatasetProbeCommand;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceBoundingBoxData;
use App\Modules\FaceSearch\Services\FaceDetectionProviderInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

it('runs a real detection probe against a dataset manifest and stores a structured report', function () {
    $fixtureRoot = storage_path('app/testing/face-search-detection-probe');
    $reportDir = storage_path('app/testing/face-search-detection-probe-reports');

    File::deleteDirectory($fixtureRoot);
    File::deleteDirectory($reportDir);
    File::ensureDirectoryExists($fixtureRoot);

    foreach (['cofw-a.png', 'wider-b.png'] as $name) {
        $file = UploadedFile::fake()->image($name, 256, 256)->size(120);
        copy($file->getPathname(), $fixtureRoot . DIRECTORY_SEPARATOR . $name);
    }

    $manifestPath = $fixtureRoot . DIRECTORY_SEPARATOR . 'manifest.json';
    File::put($manifestPath, json_encode([
        'dataset' => 'mixed-detection-probe',
        'lane' => 'detection',
        'entries' => [
            [
                'id' => 'cofw-a',
                'split' => 'test',
                'relative_path' => 'cofw-a.png',
                'scene_type' => 'portrait_occluded',
                'quality_label' => 'occluded',
                'occlusion_rate' => 0.45,
                'bbox' => ['x' => 20, 'y' => 20, 'width' => 100, 'height' => 100],
                'face_span_min_px' => 100,
            ],
            [
                'id' => 'wider-b',
                'split' => 'validation',
                'relative_path' => 'wider-b.png',
                'scene_type' => 'crowd_dense',
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
                'cofw-a.png' => [
                    new DetectedFaceData(
                        boundingBox: new FaceBoundingBoxData(18, 18, 104, 104),
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

    $this->artisan(RunDetectionDatasetProbeCommand::class, [
        '--manifest' => $manifestPath,
        '--selection' => 'highest_occlusion',
        '--occlusion-buckets' => 'heavy',
        '--report-dir' => $reportDir,
    ])->assertSuccessful();

    $reports = File::files($reportDir);

    expect($reports)->toHaveCount(1);

    $report = json_decode((string) File::get($reports[0]->getPathname()), true, 512, JSON_THROW_ON_ERROR);
    $occlusionBreakdown = collect($report['occlusion_breakdown'])->keyBy('bucket');
    $densityBreakdown = collect($report['density_breakdown'])->keyBy('bucket');

    expect($report['request_outcome'])->toBe('success')
        ->and($report['filters']['occlusion_buckets'])->toBe(['heavy'])
        ->and($report['summary']['images_sampled'])->toBe(1)
        ->and($report['summary']['annotated_faces_total'])->toBe(1)
        ->and($report['summary']['detected_faces_total'])->toBe(1)
        ->and($report['summary']['matched_annotations_total'])->toBe(1)
        ->and($report['summary']['annotation_recall_estimated'])->toBe(1)
        ->and($report['summary']['detection_precision_estimated'])->toBe(1)
        ->and($occlusionBreakdown->get('heavy')['images'])->toBe(1)
        ->and($densityBreakdown->get('single')['images'])->toBe(1);
});

it('fails clearly when the detection probe receives an invalid manifest path', function () {
    $this->artisan(RunDetectionDatasetProbeCommand::class, [
        '--manifest' => storage_path('app/testing/missing-face-search-detection-probe/manifest.json'),
    ])->assertFailed();
});
