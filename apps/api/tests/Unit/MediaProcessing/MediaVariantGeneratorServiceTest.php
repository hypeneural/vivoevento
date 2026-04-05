<?php

use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use App\Modules\MediaProcessing\Services\MediaVariantGeneratorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('generates fast preview alongside canonical image variants', function () {
    Storage::fake('public');

    $event = Event::factory()->active()->create();

    $path = UploadedFile::fake()
        ->image('entrada.jpg', 1800, 1200)
        ->store("events/{$event->id}/originals", 'public');

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'media_type' => 'image',
        'original_filename' => 'entrada.jpg',
        'original_disk' => 'public',
        'original_path' => $path,
    ]);

    $summary = app(MediaVariantGeneratorService::class)->generate($media);

    expect($summary['generated_count'])->toBe(4)
        ->and($summary['variant_keys'])->toBe(['fast_preview', 'thumb', 'gallery', 'wall'])
        ->and($summary['perceptual_hash'])->not->toBeNull();

    $fastPreview = EventMediaVariant::query()
        ->where('event_media_id', $media->id)
        ->where('variant_key', 'fast_preview')
        ->first();

    expect($fastPreview)->not->toBeNull()
        ->and($fastPreview?->mime_type)->toBe('image/webp')
        ->and($fastPreview?->width)->toBeLessThanOrEqual(512)
        ->and($fastPreview?->height)->toBeLessThanOrEqual(512);

    Storage::disk('public')->assertExists("events/{$event->id}/variants/{$media->id}/fast_preview.webp");
    Storage::disk('public')->assertExists("events/{$event->id}/variants/{$media->id}/thumb.webp");
    Storage::disk('public')->assertExists("events/{$event->id}/variants/{$media->id}/gallery.webp");
    Storage::disk('public')->assertExists("events/{$event->id}/variants/{$media->id}/wall.webp");
});
