<?php

use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use App\Modules\Wall\Enums\WallAcceptedOrientation;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\Wall\Services\WallEligibilityService;
use Illuminate\Support\Collection;

beforeEach(function () {
    config()->set('media_processing.wall_video.enabled', true);
    config()->set('media_processing.wall_video.max_duration_seconds', 30);

    $this->service = app(WallEligibilityService::class);
});

test('matchesOrientationRule returns true when accepted_orientation is all', function () {
    $settings = makeWallSettings(WallAcceptedOrientation::All);
    $media = makeWallMedia(width: 1920, height: 1080);

    expect($this->service->matchesOrientationRule($media, $settings))->toBeTrue();
});

test('matchesOrientationRule returns true for horizontal media with landscape filter', function () {
    $settings = makeWallSettings(WallAcceptedOrientation::Landscape);
    $media = makeWallMedia(width: 1920, height: 1080);

    expect($this->service->matchesOrientationRule($media, $settings))->toBeTrue();
});

test('matchesOrientationRule returns false for vertical media with landscape filter', function () {
    $settings = makeWallSettings(WallAcceptedOrientation::Landscape);
    $media = makeWallMedia(width: 1080, height: 1920);

    expect($this->service->matchesOrientationRule($media, $settings))->toBeFalse();
});

test('matchesOrientationRule returns true for vertical media with portrait filter', function () {
    $settings = makeWallSettings(WallAcceptedOrientation::Portrait);
    $media = makeWallMedia(width: 1080, height: 1920);

    expect($this->service->matchesOrientationRule($media, $settings))->toBeTrue();
});

test('matchesOrientationRule returns false for horizontal media with portrait filter', function () {
    $settings = makeWallSettings(WallAcceptedOrientation::Portrait);
    $media = makeWallMedia(width: 1920, height: 1080);

    expect($this->service->matchesOrientationRule($media, $settings))->toBeFalse();
});

test('matchesOrientationRule returns true for squareish media with any filter', function () {
    $media = makeWallMedia(width: 1000, height: 1000);

    foreach (WallAcceptedOrientation::cases() as $orientation) {
        $settings = makeWallSettings($orientation);

        expect($this->service->matchesOrientationRule($media, $settings))
            ->toBeTrue("Square media should pass {$orientation->value} filter");
    }
});

test('matchesOrientationRule returns true for unknown dimensions with any filter', function () {
    $media = makeWallMedia(width: null, height: null);

    foreach (WallAcceptedOrientation::cases() as $orientation) {
        $settings = makeWallSettings($orientation);

        expect($this->service->matchesOrientationRule($media, $settings))
            ->toBeTrue("Unknown dimensions should pass {$orientation->value} filter");
    }
});

test('mediaCanAppear blocks videos when wall video support is disabled', function () {
    $settings = makeWallSettings(overrides: ['video_enabled' => false]);
    $media = makeVideoMediaWithVariants();

    expect($this->service->mediaCanAppear($media, $settings))->toBeFalse();
});

test('mediaCanAppear blocks original-only videos when strict wall video gate is enabled', function () {
    $settings = makeWallSettings();
    $media = makeVideoMediaWithVariants(variants: []);

    expect($this->service->mediaCanAppear($media, $settings))->toBeFalse();
});

test('mediaCanAppear allows original fallback when policy explicitly enables it', function () {
    $settings = makeWallSettings(overrides: ['video_preferred_variant' => 'original']);
    $media = makeVideoMediaWithVariants(variants: []);

    expect($this->service->mediaCanAppear($media, $settings))->toBeTrue();
});

test('mediaCanAppear blocks videos without minimum metadata when policy requires it', function () {
    $settings = makeWallSettings();
    $media = makeVideoMediaWithVariants(
        width: null,
        height: null,
        durationSeconds: null,
    );

    expect($this->service->mediaCanAppear($media, $settings))->toBeFalse();
});

test('mediaCanAppear allows well-prepared short wall videos', function () {
    $settings = makeWallSettings();
    $media = makeVideoMediaWithVariants();

    expect($this->service->mediaCanAppear($media, $settings))->toBeTrue();
});

function makeWallSettings(?WallAcceptedOrientation $orientation = null, array $overrides = []): EventWallSetting
{
    $settings = new EventWallSetting();
    $settings->accepted_orientation = $orientation ?? WallAcceptedOrientation::All;
    $settings->is_enabled = true;
    $settings->status = \App\Modules\Wall\Enums\WallStatus::Live;
    $settings->video_enabled = true;
    $settings->video_playback_mode = 'play_to_end_if_short_else_cap';
    $settings->video_max_seconds = 30;
    $settings->video_resume_mode = 'resume_if_same_item_else_restart';
    $settings->video_audio_policy = 'muted';
    $settings->video_multi_layout_policy = 'disallow';
    $settings->video_preferred_variant = 'wall_video_720p';
    $settings->setRelation('event', new class
    {
        public function isActive(): bool
        {
            return true;
        }

        public function isModuleEnabled(string $module): bool
        {
            return $module === 'wall';
        }
    });

    foreach ($overrides as $key => $value) {
        $settings->{$key} = $value;
    }

    return $settings;
}

function makeWallMedia(?int $width = 1920, ?int $height = 1080): EventMedia
{
    return new EventMedia([
        'media_type' => 'image',
        'width' => $width,
        'height' => $height,
        'publication_status' => PublicationStatus::Published,
        'moderation_status' => ModerationStatus::Approved,
    ]);
}

/**
 * @param  array<int, string>  $variants
 */
function makeVideoMediaWithVariants(
    ?int $width = 1280,
    ?int $height = 720,
    ?int $durationSeconds = 18,
    array $variants = ['wall_video_720p', 'wall_video_poster'],
): EventMedia {
    $media = new EventMedia([
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'width' => $width,
        'height' => $height,
        'duration_seconds' => $durationSeconds,
        'container' => 'mp4',
        'video_codec' => 'h264',
        'publication_status' => PublicationStatus::Published,
        'moderation_status' => ModerationStatus::Approved,
    ]);

    $media->setRelation('variants', new Collection(array_map(
        fn (string $variantKey) => new EventMediaVariant([
            'variant_key' => $variantKey,
            'path' => "events/10/variants/99/{$variantKey}.mp4",
        ]),
        $variants,
    )));

    return $media;
}
