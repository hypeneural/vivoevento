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

    expect($summary['generated_count'])->toBe(6)
        ->and($summary['variant_keys'])->toBe(['fast_preview', 'thumb', 'moderation_thumb', 'moderation_preview', 'gallery', 'wall'])
        ->and($summary['perceptual_hash'])->not->toBeNull();

    $fastPreview = EventMediaVariant::query()
        ->where('event_media_id', $media->id)
        ->where('variant_key', 'fast_preview')
        ->first();

    expect($fastPreview)->not->toBeNull()
        ->and($fastPreview?->mime_type)->toBe('image/webp')
        ->and($fastPreview?->width)->toBeLessThanOrEqual(512)
        ->and($fastPreview?->height)->toBeLessThanOrEqual(512);

    expect(
        EventMediaVariant::query()
            ->where('event_media_id', $media->id)
            ->orderBy('variant_key')
            ->get(['variant_key', 'disk', 'path', 'size_bytes'])
            ->map(fn (EventMediaVariant $variant) => [
                'variant_key' => $variant->variant_key,
                'disk' => $variant->disk,
                'path' => $variant->path,
                'size_bytes' => $variant->size_bytes,
            ])
            ->all()
    )->toBe([
        [
            'variant_key' => 'fast_preview',
            'disk' => 'public',
            'path' => "events/{$event->id}/variants/{$media->id}/fast_preview.webp",
            'size_bytes' => EventMediaVariant::query()->where('event_media_id', $media->id)->where('variant_key', 'fast_preview')->value('size_bytes'),
        ],
        [
            'variant_key' => 'gallery',
            'disk' => 'public',
            'path' => "events/{$event->id}/variants/{$media->id}/gallery.webp",
            'size_bytes' => EventMediaVariant::query()->where('event_media_id', $media->id)->where('variant_key', 'gallery')->value('size_bytes'),
        ],
        [
            'variant_key' => 'moderation_preview',
            'disk' => 'public',
            'path' => "events/{$event->id}/variants/{$media->id}/moderation_preview.webp",
            'size_bytes' => EventMediaVariant::query()->where('event_media_id', $media->id)->where('variant_key', 'moderation_preview')->value('size_bytes'),
        ],
        [
            'variant_key' => 'moderation_thumb',
            'disk' => 'public',
            'path' => "events/{$event->id}/variants/{$media->id}/moderation_thumb.webp",
            'size_bytes' => EventMediaVariant::query()->where('event_media_id', $media->id)->where('variant_key', 'moderation_thumb')->value('size_bytes'),
        ],
        [
            'variant_key' => 'thumb',
            'disk' => 'public',
            'path' => "events/{$event->id}/variants/{$media->id}/thumb.webp",
            'size_bytes' => EventMediaVariant::query()->where('event_media_id', $media->id)->where('variant_key', 'thumb')->value('size_bytes'),
        ],
        [
            'variant_key' => 'wall',
            'disk' => 'public',
            'path' => "events/{$event->id}/variants/{$media->id}/wall.webp",
            'size_bytes' => EventMediaVariant::query()->where('event_media_id', $media->id)->where('variant_key', 'wall')->value('size_bytes'),
        ],
    ]);

    expect(
        EventMediaVariant::query()
            ->where('event_media_id', $media->id)
            ->pluck('size_bytes')
            ->every(fn ($size) => is_int($size) && $size > 0)
    )->toBeTrue();
});
