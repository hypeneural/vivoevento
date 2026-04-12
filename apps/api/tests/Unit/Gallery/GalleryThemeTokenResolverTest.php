<?php

use App\Modules\Gallery\Support\GalleryThemeTokenResolver;

it('resolves semantic theme tokens with accessibility defaults', function () {
    $tokens = (new GalleryThemeTokenResolver())->resolve('wedding-rose');

    expect($tokens)
        ->toHaveKeys(['palette', 'typography', 'radius', 'borders', 'shadows', 'contrast_rules', 'motion'])
        ->and($tokens['palette']['accent'])->toBe('#d97786')
        ->and($tokens['contrast_rules'])->toBe([
            'body_text_min_ratio' => 4.5,
            'large_text_min_ratio' => 3,
            'ui_min_ratio' => 3,
        ])
        ->and($tokens['motion']['respect_user_preference'])->toBeTrue();
});

it('uses event branding colors for event-brand defaults', function () {
    $tokens = (new GalleryThemeTokenResolver())->resolve('event-brand', '#123456', '#abcdef');

    expect($tokens['palette']['button_fill'])->toBe('#123456')
        ->and($tokens['palette']['accent'])->toBe('#abcdef');
});

it('ignores invalid color overrides and unknown token keys', function () {
    $tokens = (new GalleryThemeTokenResolver())->resolve('corporate-clean', null, null, [
        'palette' => [
            'accent' => 'not-a-color',
            'button_fill' => '#111111',
            'custom_css' => 'body { display: none; }',
        ],
    ]);

    expect($tokens['palette']['accent'])->toBe('#0f766e')
        ->and($tokens['palette']['button_fill'])->toBe('#111111')
        ->and($tokens['palette'])->not->toHaveKey('custom_css');
});
