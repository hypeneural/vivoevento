<?php

use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceBoundingBoxData;
use App\Modules\FaceSearch\Services\AwsFaceCropIndexSourceBuilder;
use App\Modules\FaceSearch\Services\AwsImagePreprocessor;
use App\Modules\FaceSearch\Services\FaceDetectionProviderInterface;
use App\Modules\FaceSearch\Services\FaceQualityGateService;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('builds prioritized face crops for the social gallery aws index profile', function () {
    Storage::fake('public');

    $event = \App\Modules\Events\Models\Event::factory()->create();
    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'provider_key' => 'compreface',
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_index_profile_key' => 'social_gallery_event',
        'min_face_size_px' => 80,
        'min_quality_score' => 0.60,
    ]);

    $path = UploadedFile::fake()
        ->image('gallery.jpg', 1600, 900)
        ->store("events/{$event->id}/variants/611", 'public');

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'media_type' => 'image',
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $media->id,
        'variant_key' => 'gallery',
        'disk' => 'public',
        'path' => $path,
        'width' => 1600,
        'height' => 900,
        'size_bytes' => Storage::disk('public')->size($path),
        'mime_type' => 'image/jpeg',
    ]);

    $source = [
        'binary' => Storage::disk('public')->get($path),
        'source_ref' => "public:{$path}",
    ];

    $detector = new class implements FaceDetectionProviderInterface
    {
        public function detect(EventMedia $media, \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings, string $binary): array
        {
            return [
                new DetectedFaceData(
                    boundingBox: new FaceBoundingBoxData(40, 50, 180, 180),
                    qualityScore: 0.95,
                ),
                new DetectedFaceData(
                    boundingBox: new FaceBoundingBoxData(320, 60, 170, 170),
                    qualityScore: 0.92,
                    isPrimaryCandidate: true,
                ),
                new DetectedFaceData(
                    boundingBox: new FaceBoundingBoxData(620, 90, 60, 60),
                    qualityScore: 0.91,
                ),
            ];
        }
    };

    $builder = new AwsFaceCropIndexSourceBuilder(
        $detector,
        new FaceQualityGateService,
        new AwsImagePreprocessor,
    );

    $inputs = $builder->build($media, $settings, $source);

    expect($inputs)->toHaveCount(2)
        ->and($inputs[0]['source_kind'])->toBe('face_crop')
        ->and($inputs[0]['source_ref'])->toContain('#face:0@1.8')
        ->and($inputs[0]['local_quality_tier'])->toBe('search_priority')
        ->and($inputs[1]['source_ref'])->toContain('#face:1@1.8')
        ->and($inputs[0]['width'])->toBeGreaterThan(170)
        ->and($inputs[0]['height'])->toBeGreaterThan(170);
});

it('falls back to the prepared source image when the crop profile is disabled', function () {
    Storage::fake('public');

    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'provider_key' => 'noop',
        'aws_index_profile_key' => 'default',
    ]);

    $path = UploadedFile::fake()
        ->image('source.jpg', 1200, 900)
        ->store('fallback', 'public');

    $builder = new AwsFaceCropIndexSourceBuilder(
        new class implements FaceDetectionProviderInterface
        {
            public function detect(EventMedia $media, \App\Modules\FaceSearch\Models\EventFaceSearchSetting $settings, string $binary): array
            {
                return [];
            }
        },
        new FaceQualityGateService,
        new AwsImagePreprocessor,
    );

    $inputs = $builder->build(
        EventMedia::factory()->create(['media_type' => 'image']),
        $settings,
        [
            'binary' => Storage::disk('public')->get($path),
            'source_ref' => "public:{$path}",
        ],
    );

    expect($inputs)->toHaveCount(1)
        ->and($inputs[0]['source_kind'])->toBe('source_image')
        ->and($inputs[0]['source_ref'])->toBe("public:{$path}")
        ->and($inputs[0]['face_index'])->toBeNull();
});
