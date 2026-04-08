<?php

use App\Modules\FaceSearch\Console\RunFaceSizeThresholdSweepCommand;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceBoundingBoxData;
use App\Modules\FaceSearch\Services\FaceDetectionProviderInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

it('runs a min-face-size sweep against a local dataset and stores a structured report', function () {
    $fixtureRoot = storage_path('app/testing/face-search-min-face-size-dataset');
    $reportDir = storage_path('app/testing/face-search-min-face-size-reports');

    File::deleteDirectory($fixtureRoot);
    File::deleteDirectory($reportDir);
    File::ensureDirectoryExists($fixtureRoot);

    foreach (['small-a.jpg', 'group-b.jpg', 'dense-c.jpg'] as $name) {
        $file = UploadedFile::fake()->image($name, 128, 128)->size(120);
        copy($file->getPathname(), $fixtureRoot . DIRECTORY_SEPARATOR . $name);
    }

    $groundTruthPath = $fixtureRoot . DIRECTORY_SEPARATOR . 'WebFaces_GroundThruth.txt';
    File::put($groundTruthPath, implode(PHP_EOL, [
        'small-a.jpg 10 10 30 10 20 20 20 30',
        'group-b.jpg 10 10 40 10 25 25 25 40',
        'group-b.jpg 50 10 80 10 65 25 65 40',
        'dense-c.jpg 10 10 22 10 16 16 16 22',
        'dense-c.jpg 30 10 42 10 36 16 36 22',
        'dense-c.jpg 50 10 62 10 56 16 56 22',
    ]));

    app()->instance(FaceDetectionProviderInterface::class, new class implements FaceDetectionProviderInterface
    {
        public function detect(
            \App\Modules\MediaProcessing\Models\EventMedia $media,
            \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings,
            string $binary,
        ): array {
            return match ((string) $media->original_filename) {
                'small-a.jpg' => [
                    new DetectedFaceData(
                        boundingBox: new FaceBoundingBoxData(10, 10, 52, 52),
                        detectionConfidence: 0.99,
                        qualityScore: 0.99,
                    ),
                ],
                'group-b.jpg' => [
                    new DetectedFaceData(
                        boundingBox: new FaceBoundingBoxData(10, 10, 44, 44),
                        detectionConfidence: 0.99,
                        qualityScore: 0.99,
                    ),
                    new DetectedFaceData(
                        boundingBox: new FaceBoundingBoxData(60, 10, 78, 78),
                        detectionConfidence: 0.99,
                        qualityScore: 0.99,
                    ),
                ],
                default => [
                    new DetectedFaceData(
                        boundingBox: new FaceBoundingBoxData(10, 10, 31, 31),
                        detectionConfidence: 0.99,
                        qualityScore: 0.99,
                    ),
                    new DetectedFaceData(
                        boundingBox: new FaceBoundingBoxData(45, 10, 36, 36),
                        detectionConfidence: 0.99,
                        qualityScore: 0.99,
                    ),
                    new DetectedFaceData(
                        boundingBox: new FaceBoundingBoxData(80, 10, 49, 49),
                        detectionConfidence: 0.99,
                        qualityScore: 0.99,
                    ),
                ],
            };
        }
    });

    $this->artisan(RunFaceSizeThresholdSweepCommand::class, [
        '--dataset-root' => $fixtureRoot,
        '--ground-truth' => $groundTruthPath,
        '--thresholds' => '32,48,64',
        '--limit' => 3,
        '--selection' => 'smallest_annotated_faces',
        '--report-dir' => $reportDir,
    ])->assertSuccessful();

    $reports = File::files($reportDir);

    expect($reports)->toHaveCount(1);

    $report = json_decode((string) File::get($reports[0]->getPathname()), true, 512, JSON_THROW_ON_ERROR);
    $thresholdBreakdown = collect($report['threshold_breakdown'])->keyBy('threshold');

    expect($report['request_outcome'])->toBe('success')
        ->and($report['sample_size'])->toBe(3)
        ->and($report['summary']['images_with_successful_detection'])->toBe(3)
        ->and($report['summary']['annotated_faces_total'])->toBe(6)
        ->and($report['summary']['detected_faces_total'])->toBe(6)
        ->and($thresholdBreakdown->get(32)['detected_faces_gte_threshold'])->toBe(5)
        ->and($thresholdBreakdown->get(48)['detected_faces_gte_threshold'])->toBe(3)
        ->and($thresholdBreakdown->get(64)['detected_faces_gte_threshold'])->toBe(1)
        ->and($thresholdBreakdown->get(64)['images_with_any_detected_face_gte_threshold'])->toBe(1)
        ->and($report['smallest_detected_faces'][0]['image_name'])->toBe('dense-c.jpg');
});

it('fails clearly when the face-size sweep receives an invalid dataset path', function () {
    $this->artisan(RunFaceSizeThresholdSweepCommand::class, [
        '--dataset-root' => storage_path('app/testing/missing-face-search-sweep-dataset'),
        '--ground-truth' => storage_path('app/testing/missing-face-search-sweep/WebFaces_GroundThruth.txt'),
    ])->assertFailed();
});
