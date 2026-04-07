<?php

namespace Tests\Unit\Modules\Wall;

use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Wall\Enums\WallAcceptedOrientation;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\Wall\Services\WallEligibilityService;

beforeEach(function () {
    $this->service = new WallEligibilityService();
});

test('matchesOrientationRule returns true when accepted_orientation is all', function () {
    $settings = new EventWallSetting();
    $settings->accepted_orientation = WallAcceptedOrientation::All;

    $media = new EventMedia();
    $media->width = 1920;
    $media->height = 1080;

    expect($this->service->matchesOrientationRule($media, $settings))->toBeTrue();
});

test('matchesOrientationRule returns true for horizontal media with landscape filter', function () {
    $settings = new EventWallSetting();
    $settings->accepted_orientation = WallAcceptedOrientation::Landscape;

    $media = new EventMedia();
    $media->width = 1920;
    $media->height = 1080;

    expect($this->service->matchesOrientationRule($media, $settings))->toBeTrue();
});

test('matchesOrientationRule returns false for vertical media with landscape filter', function () {
    $settings = new EventWallSetting();
    $settings->accepted_orientation = WallAcceptedOrientation::Landscape;

    $media = new EventMedia();
    $media->width = 1080;
    $media->height = 1920;

    expect($this->service->matchesOrientationRule($media, $settings))->toBeFalse();
});

test('matchesOrientationRule returns true for vertical media with portrait filter', function () {
    $settings = new EventWallSetting();
    $settings->accepted_orientation = WallAcceptedOrientation::Portrait;

    $media = new EventMedia();
    $media->width = 1080;
    $media->height = 1920;

    expect($this->service->matchesOrientationRule($media, $settings))->toBeTrue();
});

test('matchesOrientationRule returns false for horizontal media with portrait filter', function () {
    $settings = new EventWallSetting();
    $settings->accepted_orientation = WallAcceptedOrientation::Portrait;

    $media = new EventMedia();
    $media->width = 1920;
    $media->height = 1080;

    expect($this->service->matchesOrientationRule($media, $settings))->toBeFalse();
});

test('matchesOrientationRule returns true for squareish media with any filter', function () {
    $media = new EventMedia();
    $media->width = 1000;
    $media->height = 1000;

    foreach (WallAcceptedOrientation::cases() as $orientation) {
        $settings = new EventWallSetting();
        $settings->accepted_orientation = $orientation;

        expect($this->service->matchesOrientationRule($media, $settings))
            ->toBeTrue("Square media should pass {$orientation->value} filter");
    }
});

test('matchesOrientationRule returns true for unknown dimensions with any filter', function () {
    $media = new EventMedia();
    $media->width = null;
    $media->height = null;

    foreach (WallAcceptedOrientation::cases() as $orientation) {
        $settings = new EventWallSetting();
        $settings->accepted_orientation = $orientation;

        expect($this->service->matchesOrientationRule($media, $settings))
            ->toBeTrue("Unknown dimensions should pass {$orientation->value} filter");
    }
});
