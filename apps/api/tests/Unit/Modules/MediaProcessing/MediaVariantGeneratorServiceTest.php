<?php

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaVariantGeneratorService;
use App\Modules\MediaProcessing\Services\PerceptualHashService;

it('returns early without generating variants for video media', function () {
    $service = new MediaVariantGeneratorService(\Mockery::mock(PerceptualHashService::class));

    $media = new EventMedia([
        'media_type' => 'video',
        'width' => 1920,
        'height' => 1080,
    ]);

    expect($service->generate($media))->toBe([
        'generated_count' => 0,
        'variant_keys' => [],
        'source_width' => 1920,
        'source_height' => 1080,
        'perceptual_hash' => null,
    ]);
});
