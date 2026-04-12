<?php

use App\Modules\Gallery\Support\GalleryBuilderSchemaRegistry;

it('freezes the gallery builder enum catalog', function () {
    $catalog = (new GalleryBuilderSchemaRegistry())->allowedEnums();

    expect($catalog['event_type_family'])->toBe(['wedding', 'quince', 'corporate'])
        ->and($catalog['style_skin'])->toContain('romantic', 'modern', 'premium', 'clean')
        ->and($catalog['behavior_profile'])->toBe(['light', 'story', 'live', 'sponsors'])
        ->and($catalog['layout_key'])->toContain('editorial-masonry', 'timeless-rows', 'clean-columns', 'justified-story', 'live-stream')
        ->and($catalog['video_mode'])->toBe(['poster_only', 'poster_to_modal', 'inline_preview']);
});

it('exposes a base experience split into theme page and media layers', function () {
    $experience = (new GalleryBuilderSchemaRegistry())->baseExperience();

    expect($experience)
        ->toHaveKeys(['version', 'theme_key', 'layout_key', 'theme_tokens', 'page_schema', 'media_behavior'])
        ->and($experience['theme_tokens'])->toHaveKeys(['palette', 'typography', 'contrast_rules', 'motion'])
        ->and($experience['page_schema'])->toHaveKeys(['block_order', 'blocks', 'presence_rules'])
        ->and($experience['media_behavior'])->toHaveKeys(['grid', 'pagination', 'loading', 'lightbox', 'video', 'interstitials'])
        ->and($experience['media_behavior']['lightbox']['photos'])->toBeTrue()
        ->and($experience['media_behavior']['lightbox']['videos'])->toBeFalse()
        ->and($experience['media_behavior']['video']['mode'])->toBe('poster_to_modal');
});

it('freezes responsive source and mobile budget contracts', function () {
    $registry = new GalleryBuilderSchemaRegistry();

    expect($registry->responsiveSourceContract()['sizes'])->toBe('(max-width: 640px) 50vw, (max-width: 1200px) 33vw, 25vw')
        ->and($registry->responsiveSourceContract()['required_variant_fields'])->toBe(['variant_key', 'src', 'width', 'height', 'mime_type'])
        ->and($registry->mobileBudget())->toBe([
            'lcp_ms' => 2500,
            'inp_ms' => 200,
            'cls' => 0.1,
            'percentile' => 75,
        ])
        ->and($registry->optimizedRendererTrigger())->toBe([
            'item_count' => 500,
            'estimated_rendered_height_px' => 24000,
        ]);
});
