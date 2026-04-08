<?php

use App\Modules\FaceSearch\Console\RunOrganizeLocalGalleryByFaceCommand;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceBoundingBoxData;
use App\Modules\FaceSearch\DTOs\FaceEmbeddingData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Services\FaceDetectionProviderInterface;
use App\Modules\FaceSearch\Services\FaceEmbeddingProviderInterface;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

it('clusters a local gallery into pessoa folders and keeps no-face images only in the source folder', function () {
    $inputDirectory = storage_path('app/testing/face-search-organizer-input');
    $outputDirectory = storage_path('app/testing/face-search-organizer-output');

    File::deleteDirectory($inputDirectory);
    File::deleteDirectory($outputDirectory);
    File::ensureDirectoryExists($inputDirectory);

    foreach (['img-a.jpg', 'img-b.jpg', 'img-group.jpg', 'img-decor.jpg'] as $name) {
        $file = UploadedFile::fake()->image($name, 400, 300)->size(500);
        copy($file->getPathname(), $inputDirectory . DIRECTORY_SEPARATOR . $name);
    }

    app()->instance(FaceDetectionProviderInterface::class, new class implements FaceDetectionProviderInterface
    {
        public function detect(EventMedia $media, EventFaceSearchSetting $settings, string $binary): array
        {
            return match ((string) $media->original_filename) {
                'img-a.jpg' => [
                    new DetectedFaceData(
                        boundingBox: new FaceBoundingBoxData(10, 10, 120, 120),
                        detectionConfidence: 0.99,
                        qualityScore: 0.99,
                        providerEmbedding: [1.0, 0.0, 0.0],
                    ),
                ],
                'img-b.jpg' => [
                    new DetectedFaceData(
                        boundingBox: new FaceBoundingBoxData(15, 15, 118, 118),
                        detectionConfidence: 0.99,
                        qualityScore: 0.99,
                        providerEmbedding: [0.99, 0.01, 0.0],
                    ),
                ],
                'img-group.jpg' => [
                    new DetectedFaceData(
                        boundingBox: new FaceBoundingBoxData(10, 10, 110, 110),
                        detectionConfidence: 0.99,
                        qualityScore: 0.99,
                        providerEmbedding: [1.0, 0.0, 0.0],
                    ),
                    new DetectedFaceData(
                        boundingBox: new FaceBoundingBoxData(180, 10, 110, 110),
                        detectionConfidence: 0.99,
                        qualityScore: 0.99,
                        providerEmbedding: [0.0, 1.0, 0.0],
                    ),
                ],
                default => [],
            };
        }
    });

    app()->instance(FaceEmbeddingProviderInterface::class, new class implements FaceEmbeddingProviderInterface
    {
        public function embed(EventMedia $media, EventFaceSearchSetting $settings, string $cropBinary, DetectedFaceData $face): FaceEmbeddingData
        {
            return new FaceEmbeddingData(
                vector: $face->providerEmbedding,
                providerKey: 'test',
                providerVersion: 'test-v1',
                modelKey: 'test-model',
                modelSnapshot: 'test-model',
                embeddingVersion: 'test-v1',
            );
        }
    });

    $this->artisan(RunOrganizeLocalGalleryByFaceCommand::class, [
        '--input-dir' => $inputDirectory,
        '--output-dir' => $outputDirectory,
        '--cluster-threshold' => '0.10',
        '--min-face-size' => 24,
        '--min-quality-score' => 0.5,
    ])->assertSuccessful();

    $report = json_decode((string) File::get($outputDirectory . DIRECTORY_SEPARATOR . 'report.json'), true, 512, JSON_THROW_ON_ERROR);
    $folders = collect(File::directories($outputDirectory))->map(fn (string $path): string => basename($path))->sort()->values()->all();

    expect($report['summary']['images_total'])->toBe(4)
        ->and($report['summary']['images_clustered'])->toBe(3)
        ->and($report['summary']['images_without_faces'])->toBe(1)
        ->and($report['summary']['clusters_total'])->toBe(2)
        ->and($folders)->toBe(['pessoa-001', 'pessoa-002'])
        ->and(File::exists($outputDirectory . DIRECTORY_SEPARATOR . 'pessoa-001' . DIRECTORY_SEPARATOR . 'img-a.jpg'))->toBeTrue()
        ->and(File::exists($outputDirectory . DIRECTORY_SEPARATOR . 'pessoa-001' . DIRECTORY_SEPARATOR . 'img-b.jpg'))->toBeTrue()
        ->and(File::exists($outputDirectory . DIRECTORY_SEPARATOR . 'pessoa-001' . DIRECTORY_SEPARATOR . 'img-group.jpg'))->toBeTrue()
        ->and(File::exists($outputDirectory . DIRECTORY_SEPARATOR . 'pessoa-002' . DIRECTORY_SEPARATOR . 'img-group.jpg'))->toBeTrue()
        ->and(File::exists($outputDirectory . DIRECTORY_SEPARATOR . 'pessoa-002' . DIRECTORY_SEPARATOR . 'img-decor.jpg'))->toBeFalse();
});

it('treats the provider no-face response as a no-face image instead of a failed request', function () {
    $inputDirectory = storage_path('app/testing/face-search-organizer-no-face-input');
    $outputDirectory = storage_path('app/testing/face-search-organizer-no-face-output');

    File::deleteDirectory($inputDirectory);
    File::deleteDirectory($outputDirectory);
    File::ensureDirectoryExists($inputDirectory);

    $file = UploadedFile::fake()->image('img-decor.jpg', 400, 300)->size(500);
    copy($file->getPathname(), $inputDirectory . DIRECTORY_SEPARATOR . 'img-decor.jpg');

    app()->instance(FaceDetectionProviderInterface::class, new class implements FaceDetectionProviderInterface
    {
        public function detect(EventMedia $media, EventFaceSearchSetting $settings, string $binary): array
        {
            throw new \RuntimeException('CompreFace detection request failed with status 400: No face is found in the given image');
        }
    });

    app()->instance(FaceEmbeddingProviderInterface::class, new class implements FaceEmbeddingProviderInterface
    {
        public function embed(EventMedia $media, EventFaceSearchSetting $settings, string $cropBinary, DetectedFaceData $face): FaceEmbeddingData
        {
            return new FaceEmbeddingData(
                vector: [],
                providerKey: 'test',
                providerVersion: 'test-v1',
                modelKey: 'test-model',
                modelSnapshot: 'test-model',
                embeddingVersion: 'test-v1',
            );
        }
    });

    $this->artisan(RunOrganizeLocalGalleryByFaceCommand::class, [
        '--input-dir' => $inputDirectory,
        '--output-dir' => $outputDirectory,
    ])->assertSuccessful();

    $report = json_decode((string) File::get($outputDirectory . DIRECTORY_SEPARATOR . 'report.json'), true, 512, JSON_THROW_ON_ERROR);

    expect($report['summary']['images_total'])->toBe(1)
        ->and($report['summary']['images_without_faces'])->toBe(1)
        ->and($report['summary']['images_failed_or_invalid'])->toBe(0)
        ->and($report['images'][0]['status'])->toBe('no_face')
        ->and($report['images'][0]['error'])->toBeNull();
});
