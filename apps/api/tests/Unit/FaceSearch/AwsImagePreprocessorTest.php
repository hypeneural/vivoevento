<?php

use App\Modules\FaceSearch\Services\AwsImagePreprocessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('scales down large images and keeps the aws bytes payload below the configured limits', function () {
    Storage::fake('public');

    $path = UploadedFile::fake()
        ->image('huge.png', 3200, 2400)
        ->store('tmp', 'public');

    $binary = Storage::disk('public')->get($path);

    $service = new AwsImagePreprocessor;

    $result = $service->prepare($binary, [
        'max_dimension' => 1600,
        'max_bytes' => 1_500_000,
    ]);

    expect($result['mime_type'])->toBe('image/jpeg')
        ->and($result['width'])->toBeLessThanOrEqual(1600)
        ->and($result['height'])->toBeLessThanOrEqual(1600)
        ->and($result['size_bytes'])->toBeLessThanOrEqual(1_500_000)
        ->and($result['used_derivative'])->toBeTrue();
});

it('normalizes even small images into a deterministic jpeg payload for aws bytes', function () {
    Storage::fake('public');

    $path = UploadedFile::fake()
        ->image('small.png', 640, 480)
        ->store('tmp', 'public');

    $binary = Storage::disk('public')->get($path);

    $service = new AwsImagePreprocessor;

    $result = $service->prepare($binary, [
        'max_dimension' => 1920,
        'max_bytes' => 5_242_880,
    ]);

    expect($result['mime_type'])->toBe('image/jpeg')
        ->and($result['width'])->toBe(640)
        ->and($result['height'])->toBe(480)
        ->and($result['size_bytes'])->toBeGreaterThan(0);
});
