<?php

use App\Modules\Gallery\Support\GalleryModelMatrixRegistry;

it('freezes the model matrix entry axes', function () {
    $options = (new GalleryModelMatrixRegistry())->options();

    expect($options['event_type_family'])->toBe(['wedding', 'quince', 'corporate'])
        ->and($options['style_skin'])->toBe(['romantic', 'modern', 'classic', 'premium', 'clean'])
        ->and($options['behavior_profile'])->toBe(['light', 'story', 'live', 'sponsors']);
});

it('derives human model selections into technical defaults', function () {
    $registry = new GalleryModelMatrixRegistry();

    expect($registry->derive('wedding', 'romantic', 'story'))
        ->toMatchArray([
            'derived_preset_key' => 'wedding.romantic.story',
            'theme_key' => 'wedding-rose',
            'layout_key' => 'justified-story',
            'grid_layout' => 'justified',
            'video_mode' => 'poster_to_modal',
        ])
        ->and($registry->derive('quince', 'modern', 'live'))
        ->toMatchArray([
            'derived_preset_key' => 'quince.modern.live',
            'theme_key' => 'quince-glam',
            'layout_key' => 'live-stream',
            'grid_layout' => 'masonry',
            'video_mode' => 'inline_preview',
        ])
        ->and($registry->derive('corporate', 'clean', 'sponsors'))
        ->toMatchArray([
            'derived_preset_key' => 'corporate.clean.sponsors',
            'theme_key' => 'corporate-clean',
            'layout_key' => 'timeless-rows',
            'grid_layout' => 'rows',
            'interstitial_policy' => 'sponsors',
        ]);
});

it('keeps columns out of long gallery defaults', function () {
    $registry = new GalleryModelMatrixRegistry();

    expect($registry->derive('wedding', 'premium', 'light')['grid_layout'])->toBe('masonry')
        ->and($registry->derive('quince', 'modern', 'live')['grid_layout'])->toBe('masonry')
        ->and($registry->derive('corporate', 'clean', 'light')['grid_layout'])->toBe('rows');
});

it('provides the sprint zero fixtures required by the execution plan', function () {
    $fixtures = (new GalleryModelMatrixRegistry())->fixtures();

    expect(array_keys($fixtures))->toBe([
        'wedding.romantic.story',
        'wedding.premium.light',
        'quince.modern.live',
        'corporate.clean.sponsors',
    ]);
});
