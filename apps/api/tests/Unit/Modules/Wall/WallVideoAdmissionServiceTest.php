<?php

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use App\Modules\Wall\Services\WallVideoAdmissionService;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->service = app(WallVideoAdmissionService::class);
});

it('marks a well-prepared short video as eligible', function () {
    $media = new EventMedia([
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'width' => 1280,
        'height' => 720,
        'duration_seconds' => 12,
        'container' => 'mp4',
        'video_codec' => 'h264',
        'has_audio' => true,
    ]);

    $media->setRelation('variants', new Collection([
        new EventMediaVariant([
            'variant_key' => 'wall_video_720p',
            'path' => 'events/10/variants/99/wall_video_720p.mp4',
        ]),
        new EventMediaVariant([
            'variant_key' => 'wall_video_poster',
            'path' => 'events/10/variants/99/wall_video_poster.jpg',
        ]),
    ]));

    $report = $this->service->inspect($media);

    expect($report)->toMatchArray([
        'state' => 'eligible',
        'reasons' => [],
        'has_minimum_metadata' => true,
        'supported_format' => true,
        'preferred_variant_available' => true,
        'preferred_variant_key' => 'wall_video_720p',
        'poster_available' => true,
        'poster_variant_key' => 'wall_video_poster',
        'asset_source' => 'wall_variant',
        'duration_limit_seconds' => 30,
    ]);
});

it('marks original-only videos as eligible_with_fallback when poster and wall variant are missing', function () {
    $media = new EventMedia([
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'width' => 1920,
        'height' => 1080,
        'duration_seconds' => 18,
        'container' => 'mp4',
        'video_codec' => 'h264',
    ]);

    $media->setRelation('variants', new Collection());

    $report = $this->service->inspect($media);

    expect($report['state'])->toBe('eligible_with_fallback')
        ->and($report['reasons'])->toBe(['variant_missing', 'poster_missing'])
        ->and($report['preferred_variant_available'])->toBeFalse()
        ->and($report['poster_available'])->toBeFalse()
        ->and($report['asset_source'])->toBe('original');
});

it('blocks videos that exceed the default wall duration limit', function () {
    $media = new EventMedia([
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'width' => 1920,
        'height' => 1080,
        'duration_seconds' => 95,
        'container' => 'mp4',
        'video_codec' => 'h264',
    ]);

    $media->setRelation('variants', new Collection());

    $report = $this->service->inspect($media);

    expect($report['state'])->toBe('blocked')
        ->and($report['reasons'])->toContain('duration_over_limit');
});
