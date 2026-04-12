<?php

use App\Modules\Gallery\Support\GalleryAccessibilityGuardService;
use App\Modules\Gallery\Support\GalleryThemeTokenResolver;

it('calculates WCAG contrast ratios from hex colors', function () {
    $service = new GalleryAccessibilityGuardService();

    expect($service->contrastRatio('#000000', '#ffffff'))->toBe(21.0)
        ->and($service->contrastRatio('#ffffff', '#ffffff'))->toBe(1.0)
        ->and($service->contrastRatio('invalid', '#ffffff'))->toBeNull();
});

it('passes accessible theme tokens with required motion guard', function () {
    $tokens = (new GalleryThemeTokenResolver())->resolve('corporate-clean');
    $inspection = (new GalleryAccessibilityGuardService())->inspectThemeTokens($tokens);

    expect($inspection['passes'])->toBeTrue()
        ->and($inspection['checks']['body_text']['passes'])->toBeTrue()
        ->and($inspection['checks']['large_text']['passes'])->toBeTrue()
        ->and($inspection['checks']['ui']['passes'])->toBeTrue()
        ->and($inspection['motion']['respect_user_preference'])->toBeTrue();
});

it('fails inaccessible palettes and missing reduced motion guard', function () {
    $tokens = (new GalleryThemeTokenResolver())->resolve('pearl', null, null, [
        'palette' => [
            'text_primary' => '#ffffff',
            'text_secondary' => '#ffffff',
            'page_background' => '#ffffff',
            'button_fill' => '#ffffff',
            'button_text' => '#ffffff',
        ],
        'motion' => [
            'respect_user_preference' => false,
        ],
    ]);

    $inspection = (new GalleryAccessibilityGuardService())->inspectThemeTokens($tokens);

    expect($inspection['passes'])->toBeFalse()
        ->and($inspection['checks']['body_text']['passes'])->toBeFalse()
        ->and($inspection['checks']['large_text']['passes'])->toBeFalse()
        ->and($inspection['checks']['ui']['passes'])->toBeFalse()
        ->and($inspection['motion']['respect_user_preference'])->toBeFalse();
});
