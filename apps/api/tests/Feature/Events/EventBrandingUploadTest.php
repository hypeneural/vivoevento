<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('uploads a cover image for event branding', function () {
    Storage::fake('public');

    [$user, $organization] = $this->actingAsOwner();

    $response = $this
        ->withHeaders(['Accept' => 'application/json'])
        ->post('/api/v1/events/branding-assets', [
            'kind' => 'cover',
            'file' => UploadedFile::fake()->image('capa.jpg', 2400, 1400),
        ]);

    $this->assertApiSuccess($response, 201);

    $path = $response->json('data.path');

    expect($response->json('data.kind'))->toBe('cover');
    expect($path)->toStartWith("events/branding/{$organization->id}/cover/");
    expect($path)->toEndWith('.webp');
    expect(Storage::disk('public')->exists($path))->toBeTrue();
});

it('replaces a previous logo asset from the same organization folder', function () {
    Storage::fake('public');

    [$user, $organization] = $this->actingAsOwner();

    $previousPath = "events/branding/{$organization->id}/logo/existing.webp";
    Storage::disk('public')->put($previousPath, 'old-file');

    $response = $this
        ->withHeaders(['Accept' => 'application/json'])
        ->post('/api/v1/events/branding-assets', [
            'kind' => 'logo',
            'previous_path' => $previousPath,
            'file' => UploadedFile::fake()->image('logo.png', 600, 600),
        ]);

    $this->assertApiSuccess($response, 201);

    $newPath = $response->json('data.path');

    expect(Storage::disk('public')->exists($previousPath))->toBeFalse();
    expect(Storage::disk('public')->exists($newPath))->toBeTrue();
});
