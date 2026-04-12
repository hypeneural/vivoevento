<?php

use App\Modules\Events\Models\Event;
use App\Modules\Gallery\Support\GalleryBuilderPresetRegistry;

it('builds default gallery builder settings from event branding and the model matrix', function () {
    $event = Event::factory()->make([
        'event_type' => 'corporate',
        'primary_color' => '#112233',
        'secondary_color' => '#445566',
    ]);

    $defaults = app(GalleryBuilderPresetRegistry::class)->defaultsForEvent($event);

    expect($defaults['event_type_family'])->toBe('corporate')
        ->and($defaults['style_skin'])->toBe('clean')
        ->and($defaults['behavior_profile'])->toBe('light')
        ->and($defaults['theme_key'])->toBe('event-brand')
        ->and($defaults['layout_key'])->toBe('timeless-rows')
        ->and($defaults['theme_tokens']['palette']['button_fill'])->toBe('#112233')
        ->and($defaults['theme_tokens']['palette']['accent'])->toBe('#445566')
        ->and($defaults['page_schema']['blocks']['hero']['variant'])->toBe('corporate')
        ->and($defaults['media_behavior']['grid']['layout'])->toBe('rows');
});

it('normalizes partial gallery builder settings while keeping separated layers valid', function () {
    $event = Event::factory()->make([
        'event_type' => 'wedding',
        'primary_color' => '#123456',
        'secondary_color' => '#abcdef',
    ]);

    $normalized = app(GalleryBuilderPresetRegistry::class)->normalize($event, [
        'event_type_family' => 'quince',
        'style_skin' => 'modern',
        'behavior_profile' => 'live',
        'theme_key' => 'quince-glam',
        'layout_key' => 'live-stream',
        'theme_tokens' => [
            'palette' => [
                'accent' => '#ec4899',
                'button_fill' => '#db2777',
                'custom_css' => 'display:none',
            ],
        ],
        'page_schema' => [
            'block_order' => ['hero', 'gallery_stream', 'footer_brand', 'unknown'],
            'blocks' => [
                'hero' => [
                    'variant' => 'quince',
                    'show_face_search_cta' => false,
                ],
                'banner_strip' => [
                    'enabled' => true,
                    'positions' => ['after_12', 'after_24', 'after_36'],
                ],
            ],
        ],
        'media_behavior' => [
            'grid' => [
                'layout' => 'masonry',
                'density' => 'immersive',
            ],
            'video' => [
                'mode' => 'inline_preview',
                'allow_inline_preview' => true,
            ],
        ],
    ]);

    expect($normalized['event_type_family'])->toBe('quince')
        ->and($normalized['style_skin'])->toBe('modern')
        ->and($normalized['behavior_profile'])->toBe('live')
        ->and($normalized['theme_key'])->toBe('quince-glam')
        ->and($normalized['layout_key'])->toBe('live-stream')
        ->and($normalized['theme_tokens']['palette']['accent'])->toBe('#ec4899')
        ->and($normalized['theme_tokens']['palette'])->not->toHaveKey('custom_css')
        ->and($normalized['page_schema']['block_order'])->toBe(['hero', 'gallery_stream', 'footer_brand'])
        ->and($normalized['page_schema']['blocks']['banner_strip']['positions'])->toBe(['after_12', 'after_24'])
        ->and($normalized['media_behavior']['video']['mode'])->toBe('inline_preview')
        ->and($normalized['media_behavior']['video']['allow_inline_preview'])->toBeTrue();
});
