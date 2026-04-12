<?php

declare(strict_types=1);

namespace App\Modules\Gallery\Support;

class GalleryBuilderSchemaRegistry
{
    public const EVENT_TYPE_FAMILIES = ['wedding', 'quince', 'corporate'];

    public const STYLE_SKINS = ['romantic', 'modern', 'classic', 'premium', 'clean'];

    public const BEHAVIOR_PROFILES = ['light', 'story', 'live', 'sponsors'];

    public const THEME_KEYS = [
        'event-brand',
        'pearl',
        'wedding-rose',
        'black-tie',
        'quince-glam',
        'corporate-clean',
    ];

    public const LAYOUT_KEYS = [
        'editorial-masonry',
        'timeless-rows',
        'clean-columns',
        'justified-story',
        'live-stream',
    ];

    public const BLOCK_KEYS = [
        'hero',
        'gallery_stream',
        'banner_strip',
        'info_cards',
        'quote',
        'cta_strip',
        'footer_brand',
    ];

    public const VIDEO_MODES = ['poster_only', 'poster_to_modal', 'inline_preview'];

    public const DENSITIES = ['compact', 'comfortable', 'immersive'];

    public const INTERSTITIAL_POLICIES = ['disabled', 'story', 'sponsors'];

    public const RESPONSIVE_SIZES = '(max-width: 640px) 50vw, (max-width: 1200px) 33vw, 25vw';

    /**
     * @return array<string, array<int, string>>
     */
    public function allowedEnums(): array
    {
        return [
            'event_type_family' => self::EVENT_TYPE_FAMILIES,
            'style_skin' => self::STYLE_SKINS,
            'behavior_profile' => self::BEHAVIOR_PROFILES,
            'theme_key' => self::THEME_KEYS,
            'layout_key' => self::LAYOUT_KEYS,
            'block_key' => self::BLOCK_KEYS,
            'video_mode' => self::VIDEO_MODES,
            'density' => self::DENSITIES,
            'interstitial_policy' => self::INTERSTITIAL_POLICIES,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function baseExperience(): array
    {
        return [
            'version' => 1,
            'theme_key' => 'event-brand',
            'layout_key' => 'editorial-masonry',
            'theme_tokens' => (new GalleryThemeTokenResolver())->resolve('event-brand'),
            'page_schema' => [
                'block_order' => ['hero', 'gallery_stream', 'banner_strip', 'quote', 'footer_brand'],
                'blocks' => [
                    'hero' => [
                        'enabled' => true,
                        'variant' => 'wedding',
                        'show_logo' => true,
                        'show_face_search_cta' => true,
                    ],
                    'gallery_stream' => [
                        'enabled' => true,
                    ],
                    'banner_strip' => [
                        'enabled' => false,
                        'positions' => ['after_12'],
                    ],
                    'quote' => [
                        'enabled' => false,
                    ],
                    'footer_brand' => [
                        'enabled' => true,
                    ],
                ],
                'presence_rules' => [
                    'hero_required' => true,
                    'max_banner_blocks' => 2,
                    'require_preview_before_publish' => true,
                ],
            ],
            'media_behavior' => [
                'grid' => [
                    'layout' => 'masonry',
                    'density' => 'comfortable',
                    'breakpoints' => [360, 768, 1200],
                ],
                'pagination' => [
                    'mode' => 'infinite-scroll',
                    'page_size' => 30,
                    'chunk_strategy' => 'sectioned',
                ],
                'loading' => [
                    'hero_and_first_band' => 'eager',
                    'below_fold' => 'lazy',
                    'content_visibility' => 'auto',
                ],
                'lightbox' => [
                    'photos' => true,
                    'videos' => false,
                ],
                'video' => [
                    'allowed_modes' => self::VIDEO_MODES,
                    'mode' => 'poster_to_modal',
                    'show_badge' => true,
                    'allow_inline_preview' => false,
                ],
                'interstitials' => [
                    'enabled' => false,
                    'policy' => 'disabled',
                    'max_per_24_items' => 1,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function responsiveSourceContract(): array
    {
        return [
            'sizes' => self::RESPONSIVE_SIZES,
            'required_variant_fields' => ['variant_key', 'src', 'width', 'height', 'mime_type'],
            'target_widths' => [320, 480, 768, 1024, 1440],
        ];
    }

    /**
     * @return array<string, int|float>
     */
    public function mobileBudget(): array
    {
        return [
            'lcp_ms' => 2500,
            'inp_ms' => 200,
            'cls' => 0.1,
            'percentile' => 75,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function optimizedRendererTrigger(): array
    {
        return [
            'item_count' => 500,
            'estimated_rendered_height_px' => 24000,
        ];
    }
}
