<?php

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use App\Shared\Support\AssetUrlService;
use Illuminate\Support\Collection;

it('falls back to the original video URL when no wall variant exists', function () {
    $media = new EventMedia([
        'event_id' => 42,
        'media_type' => 'video',
        'original_filename' => 'clip.mp4',
    ]);
    $media->setRelation('variants', new Collection());

    $service = new MediaAssetUrlService(new AssetUrlService());
    $thumbnailAsset = $service->thumbnailAsset($media);
    $previewAsset = $service->previewAsset($media);

    expect($service->wall($media))->toBe(rtrim((string) config('app.url'), '/').'/storage/events/42/originals/clip.mp4')
        ->and($service->thumbnail($media))->toBe(rtrim((string) config('app.url'), '/').'/storage/events/42/originals/clip.mp4')
        ->and($service->preview($media))->toBe(rtrim((string) config('app.url'), '/').'/storage/events/42/originals/clip.mp4')
        ->and($thumbnailAsset['source'])->toBe('original')
        ->and($previewAsset['source'])->toBe('original')
        ->and($service->poster($media))->toBeNull()
        ->and($service->wallVariantKey($media))->toBeNull()
        ->and($service->posterVariantKey($media))->toBeNull();
});

it('falls back to the original image URL for thumbnail and preview when optimized variants are missing', function () {
    $media = new EventMedia([
        'event_id' => 77,
        'media_type' => 'image',
        'original_filename' => 'foto.jpg',
    ]);
    $media->setRelation('variants', new Collection());

    $service = new MediaAssetUrlService(new AssetUrlService());
    $thumbnailAsset = $service->thumbnailAsset($media);
    $previewAsset = $service->previewAsset($media);

    expect($service->thumbnail($media))->toBe(rtrim((string) config('app.url'), '/').'/storage/events/77/originals/foto.jpg')
        ->and($service->preview($media))->toBe(rtrim((string) config('app.url'), '/').'/storage/events/77/originals/foto.jpg')
        ->and($thumbnailAsset['source'])->toBe('original')
        ->and($previewAsset['source'])->toBe('original')
        ->and($service->original($media))->toBe(rtrim((string) config('app.url'), '/').'/storage/events/77/originals/foto.jpg');
});

it('does not fall back to the original asset for moderation image surfaces when dedicated variants are missing', function () {
    $media = new EventMedia([
        'event_id' => 77,
        'media_type' => 'image',
        'original_filename' => 'foto.jpg',
    ]);
    $media->setRelation('variants', new Collection());

    $service = new MediaAssetUrlService(new AssetUrlService());
    $thumbnailAsset = $service->moderationThumbnailAsset($media);
    $previewAsset = $service->moderationPreviewAsset($media);

    expect($thumbnailAsset['url'])->toBeNull()
        ->and($thumbnailAsset['source'])->toBeNull()
        ->and($previewAsset['url'])->toBeNull()
        ->and($previewAsset['source'])->toBeNull()
        ->and($service->original($media))->toBe(rtrim((string) config('app.url'), '/').'/storage/events/77/originals/foto.jpg');
});

it('prefers moderation-specific image variants when they exist', function () {
    $media = new EventMedia([
        'event_id' => 77,
        'media_type' => 'image',
        'original_filename' => 'foto.jpg',
    ]);
    $media->setRelation('variants', new Collection([
        new EventMediaVariant([
            'variant_key' => 'moderation_thumb',
            'disk' => 'public',
            'path' => 'events/77/variants/11/moderation_thumb.webp',
        ]),
        new EventMediaVariant([
            'variant_key' => 'moderation_preview',
            'disk' => 'public',
            'path' => 'events/77/variants/11/moderation_preview.webp',
        ]),
        new EventMediaVariant([
            'variant_key' => 'thumb',
            'disk' => 'public',
            'path' => 'events/77/variants/11/thumb.webp',
        ]),
    ]));

    $service = new MediaAssetUrlService(new AssetUrlService());
    $thumbnailAsset = $service->moderationThumbnailAsset($media);
    $previewAsset = $service->moderationPreviewAsset($media);

    expect($thumbnailAsset['url'])->toBe(rtrim((string) config('app.url'), '/').'/storage/events/77/variants/11/moderation_thumb.webp')
        ->and($thumbnailAsset['source'])->toBe('moderation_thumb')
        ->and($previewAsset['url'])->toBe(rtrim((string) config('app.url'), '/').'/storage/events/77/variants/11/moderation_preview.webp')
        ->and($previewAsset['source'])->toBe('moderation_preview');
});

it('prefers dedicated wall video and poster variants when they exist', function () {
    $media = new EventMedia([
        'event_id' => 42,
        'media_type' => 'video',
        'original_filename' => 'clip.mp4',
    ]);
    $media->setRelation('variants', new Collection([
        new EventMediaVariant([
            'variant_key' => 'wall_video_1080p',
            'disk' => 'public',
            'path' => 'events/42/variants/99/wall_video_1080p.mp4',
        ]),
        new EventMediaVariant([
            'variant_key' => 'wall_video_720p',
            'disk' => 'public',
            'path' => 'events/42/variants/99/wall_video_720p.mp4',
        ]),
        new EventMediaVariant([
            'variant_key' => 'wall_video_poster',
            'disk' => 'public',
            'path' => 'events/42/variants/99/wall_video_poster.jpg',
        ]),
    ]));

    $service = new MediaAssetUrlService(new AssetUrlService());
    $thumbnailAsset = $service->thumbnailAsset($media);
    $previewAsset = $service->previewAsset($media);

    expect($service->wall($media))->toBe(rtrim((string) config('app.url'), '/').'/storage/events/42/variants/99/wall_video_720p.mp4')
        ->and($service->preview($media))->toBe(rtrim((string) config('app.url'), '/').'/storage/events/42/variants/99/wall_video_720p.mp4')
        ->and($service->thumbnail($media))->toBe(rtrim((string) config('app.url'), '/').'/storage/events/42/variants/99/wall_video_poster.jpg')
        ->and($thumbnailAsset['source'])->toBe('wall_video_poster')
        ->and($previewAsset['source'])->toBe('wall_video_720p')
        ->and($service->poster($media))->toBe(rtrim((string) config('app.url'), '/').'/storage/events/42/variants/99/wall_video_poster.jpg')
        ->and($service->wallVariantKey($media))->toBe('wall_video_720p')
        ->and($service->posterVariantKey($media))->toBe('wall_video_poster');
});

it('falls back to 1080p when the 720p wall variant is unavailable', function () {
    $media = new EventMedia([
        'event_id' => 42,
        'media_type' => 'video',
        'original_filename' => 'clip.mp4',
    ]);
    $media->setRelation('variants', new Collection([
        new EventMediaVariant([
            'variant_key' => 'wall_video_1080p',
            'disk' => 'public',
            'path' => 'events/42/variants/99/wall_video_1080p.mp4',
        ]),
    ]));

    $service = new MediaAssetUrlService(new AssetUrlService());

    expect($service->wall($media))->toBe(rtrim((string) config('app.url'), '/').'/storage/events/42/variants/99/wall_video_1080p.mp4')
        ->and($service->preview($media))->toBe(rtrim((string) config('app.url'), '/').'/storage/events/42/variants/99/wall_video_1080p.mp4')
        ->and($service->wallVariantKey($media))->toBe('wall_video_1080p');
});
