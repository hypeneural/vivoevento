<?php

declare(strict_types=1);

namespace App\Modules\Gallery\Support;

use App\Modules\Events\Enums\EventType;
use App\Modules\Events\Models\Event;
use Illuminate\Validation\ValidationException;

class GalleryBuilderPresetRegistry
{
    public function __construct(
        private readonly GalleryBuilderSchemaRegistry $schemaRegistry,
        private readonly GalleryModelMatrixRegistry $modelMatrixRegistry,
        private readonly GalleryThemeTokenResolver $themeTokenResolver,
        private readonly GalleryAccessibilityGuardService $accessibilityGuard,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function defaultsForEvent(Event $event): array
    {
        $selection = $this->selectionForEvent($event);
        $derived = $this->modelMatrixRegistry->derive(
            $selection['event_type_family'],
            $selection['style_skin'],
            $selection['behavior_profile'],
        );

        $experience = $this->schemaRegistry->baseExperience();
        $experience['page_schema']['blocks']['hero']['variant'] = $selection['event_type_family'];
        $experience['media_behavior']['grid']['layout'] = $derived['grid_layout'];
        $experience['media_behavior']['video']['mode'] = $derived['video_mode'];
        $experience['media_behavior']['video']['allow_inline_preview'] = $derived['video_mode'] === 'inline_preview';
        $experience['media_behavior']['interstitials']['enabled'] = $derived['interstitial_policy'] !== 'disabled';
        $experience['media_behavior']['interstitials']['policy'] = $derived['interstitial_policy'];
        $experience['theme_tokens'] = $this->themeTokenResolver->resolve(
            'event-brand',
            $event->primary_color,
            $event->secondary_color,
        );

        return [
            'is_enabled' => true,
            'event_type_family' => $selection['event_type_family'],
            'style_skin' => $selection['style_skin'],
            'behavior_profile' => $selection['behavior_profile'],
            'theme_key' => 'event-brand',
            'layout_key' => $derived['layout_key'],
            'derived_preset_key' => $derived['derived_preset_key'],
            'theme_tokens' => $experience['theme_tokens'],
            'page_schema' => $experience['page_schema'],
            'media_behavior' => $experience['media_behavior'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    public function normalize(Event $event, ?array $stored = null): array
    {
        $defaults = $this->defaultsForEvent($event);
        $stored = is_array($stored) ? $stored : [];

        $eventTypeFamily = $this->allowedOrDefault(
            $stored['event_type_family'] ?? $defaults['event_type_family'],
            GalleryBuilderSchemaRegistry::EVENT_TYPE_FAMILIES,
            $defaults['event_type_family'],
        );
        $styleSkin = $this->allowedOrDefault(
            $stored['style_skin'] ?? $defaults['style_skin'],
            GalleryBuilderSchemaRegistry::STYLE_SKINS,
            $defaults['style_skin'],
        );
        $behaviorProfile = $this->allowedOrDefault(
            $stored['behavior_profile'] ?? $defaults['behavior_profile'],
            GalleryBuilderSchemaRegistry::BEHAVIOR_PROFILES,
            $defaults['behavior_profile'],
        );

        $derived = $this->modelMatrixRegistry->derive($eventTypeFamily, $styleSkin, $behaviorProfile);
        $themeKey = $this->allowedOrDefault(
            $stored['theme_key'] ?? $defaults['theme_key'],
            GalleryBuilderSchemaRegistry::THEME_KEYS,
            $defaults['theme_key'],
        );
        $layoutKey = $this->allowedOrDefault(
            $stored['layout_key'] ?? $defaults['layout_key'],
            GalleryBuilderSchemaRegistry::LAYOUT_KEYS,
            $derived['layout_key'],
        );

        $themeTokens = $this->themeTokenResolver->resolve(
            $themeKey,
            $event->primary_color,
            $event->secondary_color,
            is_array($stored['theme_tokens'] ?? null) ? $stored['theme_tokens'] : [],
        );
        $pageSchema = $this->normalizePageSchema(
            $defaults['page_schema'],
            is_array($stored['page_schema'] ?? null) ? $stored['page_schema'] : [],
            $eventTypeFamily,
        );
        $mediaBehavior = $this->normalizeMediaBehavior(
            $defaults['media_behavior'],
            is_array($stored['media_behavior'] ?? null) ? $stored['media_behavior'] : [],
            $derived,
        );

        return [
            'is_enabled' => array_key_exists('is_enabled', $stored)
                ? (bool) $stored['is_enabled']
                : (bool) $defaults['is_enabled'],
            'event_type_family' => $eventTypeFamily,
            'style_skin' => $styleSkin,
            'behavior_profile' => $behaviorProfile,
            'theme_key' => $themeKey,
            'layout_key' => $layoutKey,
            'derived_preset_key' => $derived['derived_preset_key'],
            'theme_tokens' => $themeTokens,
            'page_schema' => $pageSchema,
            'media_behavior' => $mediaBehavior,
        ];
    }

    /**
     * @param  array<string, mixed>  $themeTokens
     */
    public function assertAccessible(array $themeTokens): void
    {
        $inspection = $this->accessibilityGuard->inspectThemeTokens($themeTokens);

        if ($inspection['passes'] === true) {
            return;
        }

        throw ValidationException::withMessages([
            'theme_tokens' => 'Os tokens da galeria nao atendem aos guardrails minimos de contraste e reduced motion.',
        ]);
    }

    /**
     * @return array{event_type_family: string, style_skin: string, behavior_profile: string}
     */
    private function selectionForEvent(Event $event): array
    {
        $eventType = $event->event_type instanceof EventType
            ? $event->event_type
            : EventType::tryFrom((string) $event->event_type);

        return match ($eventType) {
            EventType::Corporate, EventType::Fair, EventType::Graduation => [
                'event_type_family' => 'corporate',
                'style_skin' => 'clean',
                'behavior_profile' => 'light',
            ],
            EventType::Fifteen, EventType::Birthday => [
                'event_type_family' => 'quince',
                'style_skin' => 'modern',
                'behavior_profile' => 'light',
            ],
            default => [
                'event_type_family' => 'wedding',
                'style_skin' => 'romantic',
                'behavior_profile' => 'light',
            ],
        };
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function allowedOrDefault(mixed $value, array $allowed, string $default): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function normalizePageSchema(array $defaults, array $stored, string $eventTypeFamily): array
    {
        $blockOrder = collect($stored['block_order'] ?? $defaults['block_order'] ?? [])
            ->filter(fn ($value) => is_string($value) && in_array($value, GalleryBuilderSchemaRegistry::BLOCK_KEYS, true))
            ->unique()
            ->values()
            ->all();

        if ($blockOrder === []) {
            $blockOrder = $defaults['block_order'];
        }

        $hero = is_array($stored['blocks']['hero'] ?? null) ? $stored['blocks']['hero'] : [];
        $banner = is_array($stored['blocks']['banner_strip'] ?? null) ? $stored['blocks']['banner_strip'] : [];
        $quote = is_array($stored['blocks']['quote'] ?? null) ? $stored['blocks']['quote'] : [];
        $footer = is_array($stored['blocks']['footer_brand'] ?? null) ? $stored['blocks']['footer_brand'] : [];

        $positions = collect($banner['positions'] ?? $defaults['blocks']['banner_strip']['positions'] ?? [])
            ->filter(fn ($value) => is_string($value) && preg_match('/^after_\d+$/', $value))
            ->unique()
            ->take((int) ($defaults['presence_rules']['max_banner_blocks'] ?? 2))
            ->values()
            ->all();

        return [
            'block_order' => $blockOrder,
            'blocks' => [
                'hero' => [
                    'enabled' => true,
                    'variant' => $this->allowedOrDefault(
                        $hero['variant'] ?? $eventTypeFamily,
                        GalleryBuilderSchemaRegistry::EVENT_TYPE_FAMILIES,
                        $eventTypeFamily,
                    ),
                    'show_logo' => array_key_exists('show_logo', $hero)
                        ? (bool) $hero['show_logo']
                        : (bool) ($defaults['blocks']['hero']['show_logo'] ?? true),
                    'show_face_search_cta' => array_key_exists('show_face_search_cta', $hero)
                        ? (bool) $hero['show_face_search_cta']
                        : (bool) ($defaults['blocks']['hero']['show_face_search_cta'] ?? true),
                    'image_path' => $this->normalizeAssetPath(
                        $hero['image_path'] ?? ($defaults['blocks']['hero']['image_path'] ?? null),
                    ),
                ],
                'gallery_stream' => [
                    'enabled' => true,
                ],
                'banner_strip' => [
                    'enabled' => array_key_exists('enabled', $banner)
                        ? (bool) $banner['enabled']
                        : (bool) ($defaults['blocks']['banner_strip']['enabled'] ?? false),
                    'positions' => $positions,
                    'image_path' => $this->normalizeAssetPath(
                        $banner['image_path'] ?? ($defaults['blocks']['banner_strip']['image_path'] ?? null),
                    ),
                ],
                'quote' => [
                    'enabled' => array_key_exists('enabled', $quote)
                        ? (bool) $quote['enabled']
                        : (bool) ($defaults['blocks']['quote']['enabled'] ?? false),
                ],
                'footer_brand' => [
                    'enabled' => array_key_exists('enabled', $footer)
                        ? (bool) $footer['enabled']
                        : (bool) ($defaults['blocks']['footer_brand']['enabled'] ?? true),
                ],
            ],
            'presence_rules' => [
                'hero_required' => true,
                'max_banner_blocks' => (int) ($defaults['presence_rules']['max_banner_blocks'] ?? 2),
                'require_preview_before_publish' => true,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $stored
     * @param  array<string, mixed>  $derived
     * @return array<string, mixed>
     */
    private function normalizeMediaBehavior(array $defaults, array $stored, array $derived): array
    {
        $grid = is_array($stored['grid'] ?? null) ? $stored['grid'] : [];
        $pagination = is_array($stored['pagination'] ?? null) ? $stored['pagination'] : [];
        $loading = is_array($stored['loading'] ?? null) ? $stored['loading'] : [];
        $lightbox = is_array($stored['lightbox'] ?? null) ? $stored['lightbox'] : [];
        $video = is_array($stored['video'] ?? null) ? $stored['video'] : [];
        $interstitials = is_array($stored['interstitials'] ?? null) ? $stored['interstitials'] : [];

        return [
            'grid' => [
                'layout' => $this->allowedOrDefault(
                    $grid['layout'] ?? ($defaults['grid']['layout'] ?? $derived['grid_layout']),
                    ['masonry', 'rows', 'columns', 'justified'],
                    (string) ($defaults['grid']['layout'] ?? $derived['grid_layout']),
                ),
                'density' => $this->allowedOrDefault(
                    $grid['density'] ?? ($defaults['grid']['density'] ?? 'comfortable'),
                    GalleryBuilderSchemaRegistry::DENSITIES,
                    (string) ($defaults['grid']['density'] ?? 'comfortable'),
                ),
                'breakpoints' => collect($grid['breakpoints'] ?? $defaults['grid']['breakpoints'] ?? [360, 768, 1200])
                    ->filter(fn ($value) => is_numeric($value))
                    ->map(fn ($value) => (int) $value)
                    ->take(6)
                    ->values()
                    ->all(),
            ],
            'pagination' => [
                'mode' => $this->allowedOrDefault(
                    $pagination['mode'] ?? ($defaults['pagination']['mode'] ?? 'infinite-scroll'),
                    ['page', 'infinite-scroll'],
                    (string) ($defaults['pagination']['mode'] ?? 'infinite-scroll'),
                ),
                'page_size' => max(1, min(100, (int) ($pagination['page_size'] ?? ($defaults['pagination']['page_size'] ?? 30)))),
                'chunk_strategy' => $this->allowedOrDefault(
                    $pagination['chunk_strategy'] ?? ($defaults['pagination']['chunk_strategy'] ?? 'sectioned'),
                    ['page', 'sectioned'],
                    (string) ($defaults['pagination']['chunk_strategy'] ?? 'sectioned'),
                ),
            ],
            'loading' => [
                'hero_and_first_band' => 'eager',
                'below_fold' => 'lazy',
                'content_visibility' => $this->allowedOrDefault(
                    $loading['content_visibility'] ?? ($defaults['loading']['content_visibility'] ?? 'auto'),
                    ['auto', 'visible'],
                    (string) ($defaults['loading']['content_visibility'] ?? 'auto'),
                ),
            ],
            'lightbox' => [
                'photos' => array_key_exists('photos', $lightbox)
                    ? (bool) $lightbox['photos']
                    : (bool) ($defaults['lightbox']['photos'] ?? true),
                'videos' => array_key_exists('videos', $lightbox)
                    ? (bool) $lightbox['videos']
                    : (bool) ($defaults['lightbox']['videos'] ?? false),
            ],
            'video' => [
                'allowed_modes' => GalleryBuilderSchemaRegistry::VIDEO_MODES,
                'mode' => $this->allowedOrDefault(
                    $video['mode'] ?? ($defaults['video']['mode'] ?? $derived['video_mode']),
                    GalleryBuilderSchemaRegistry::VIDEO_MODES,
                    (string) ($defaults['video']['mode'] ?? $derived['video_mode']),
                ),
                'show_badge' => array_key_exists('show_badge', $video)
                    ? (bool) $video['show_badge']
                    : (bool) ($defaults['video']['show_badge'] ?? true),
                'allow_inline_preview' => array_key_exists('allow_inline_preview', $video)
                    ? (bool) $video['allow_inline_preview']
                    : ((string) ($video['mode'] ?? ($defaults['video']['mode'] ?? $derived['video_mode'])) === 'inline_preview'),
            ],
            'interstitials' => [
                'enabled' => array_key_exists('enabled', $interstitials)
                    ? (bool) $interstitials['enabled']
                    : (bool) ($defaults['interstitials']['enabled'] ?? false),
                'policy' => $this->allowedOrDefault(
                    $interstitials['policy'] ?? ($defaults['interstitials']['policy'] ?? $derived['interstitial_policy']),
                    GalleryBuilderSchemaRegistry::INTERSTITIAL_POLICIES,
                    (string) ($defaults['interstitials']['policy'] ?? $derived['interstitial_policy']),
                ),
                'max_per_24_items' => max(0, min(2, (int) ($interstitials['max_per_24_items'] ?? ($defaults['interstitials']['max_per_24_items'] ?? 1)))),
            ],
        ];
    }

    private function normalizeAssetPath(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
