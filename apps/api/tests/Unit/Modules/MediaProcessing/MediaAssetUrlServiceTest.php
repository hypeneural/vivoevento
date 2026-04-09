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

    expect($service->wall($media))->toBe(rtrim((string) config('app.url'), '/').'/storage/events/42/originals/clip.mp4');
});

it('prefers the wall variant URL for video when it exists', function () {
    $media = new EventMedia([
        'event_id' => 42,
        'media_type' => 'video',
        'original_filename' => 'clip.mp4',
    ]);
    $media->setRelation('variants', new Collection([
        new EventMediaVariant([
            'variant_key' => 'wall',
            'disk' => 'public',
            'path' => 'events/42/variants/99/wall.mp4',
        ]),
    ]));

    $service = new MediaAssetUrlService(new AssetUrlService());

    expect($service->wall($media))->toBe(rtrim((string) config('app.url'), '/').'/storage/events/42/variants/99/wall.mp4');
});
