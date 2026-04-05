<?php

namespace App\Modules\Hub\Support;

use App\Modules\Events\Models\Event;

class HubBuilderPresetRegistry
{
    public const LAYOUT_KEYS = [
        'classic-cover',
        'hero-cards',
        'minimal-center',
    ];

    public const THEME_KEYS = [
        'midnight',
        'sunset',
        'pearl',
        'wedding',
        'quince',
        'corporate',
    ];

    public const BLOCK_KEYS = [
        'hero',
        'meta_cards',
        'welcome',
        'countdown',
        'info_grid',
        'cta_list',
        'social_strip',
        'sponsor_strip',
    ];

    public function defaultsForEvent(Event $event): array
    {
        $layout = $this->layoutBlocks('classic-cover');
        $layout['blocks']['countdown']['target_at'] = $event->starts_at?->toIso8601String();
        $layout['blocks']['countdown']['enabled'] = filled($layout['blocks']['countdown']['target_at']);

        return [
            'version' => 1,
            'layout_key' => 'classic-cover',
            'theme_key' => 'midnight',
            'theme_tokens' => $this->themeTokens('midnight', $event),
            'block_order' => $layout['order'],
            'blocks' => $layout['blocks'],
        ];
    }

    public function normalize(?array $stored, Event $event): array
    {
        $defaults = $this->defaultsForEvent($event);

        if (! is_array($stored)) {
            return $defaults;
        }

        $layoutKey = in_array($stored['layout_key'] ?? null, self::LAYOUT_KEYS, true)
            ? $stored['layout_key']
            : $defaults['layout_key'];

        $themeKey = in_array($stored['theme_key'] ?? null, self::THEME_KEYS, true)
            ? $stored['theme_key']
            : $defaults['theme_key'];

        $themeDefaults = $this->themeTokens($themeKey, $event);
        $storedThemeTokens = is_array($stored['theme_tokens'] ?? null) ? $stored['theme_tokens'] : [];

        $themeTokens = [
            'page_background' => $this->colorOrDefault($storedThemeTokens['page_background'] ?? null, $themeDefaults['page_background']),
            'page_accent' => $this->colorOrDefault($storedThemeTokens['page_accent'] ?? null, $themeDefaults['page_accent']),
            'surface_background' => $this->colorOrDefault($storedThemeTokens['surface_background'] ?? null, $themeDefaults['surface_background']),
            'surface_border' => $this->colorOrDefault($storedThemeTokens['surface_border'] ?? null, $themeDefaults['surface_border']),
            'text_primary' => $this->colorOrDefault($storedThemeTokens['text_primary'] ?? null, $themeDefaults['text_primary']),
            'text_secondary' => $this->colorOrDefault($storedThemeTokens['text_secondary'] ?? null, $themeDefaults['text_secondary']),
            'hero_overlay_color' => $this->colorOrDefault($storedThemeTokens['hero_overlay_color'] ?? null, $themeDefaults['hero_overlay_color']),
        ];

        $blockOrder = collect($stored['block_order'] ?? [])
            ->filter(fn ($value) => is_string($value) && in_array($value, self::BLOCK_KEYS, true))
            ->unique()
            ->values()
            ->all();

        if ($blockOrder === []) {
            $blockOrder = $this->layoutBlocks($layoutKey)['order'];
        }

        $blockDefaults = $this->layoutBlocks($layoutKey);
        $storedBlocks = is_array($stored['blocks'] ?? null) ? $stored['blocks'] : [];

        return [
            'version' => 1,
            'layout_key' => $layoutKey,
            'theme_key' => $themeKey,
            'theme_tokens' => $themeTokens,
            'block_order' => $blockOrder,
            'blocks' => [
                'hero' => $this->normalizeHeroBlock($storedBlocks['hero'] ?? null, $blockDefaults['blocks']['hero']),
                'meta_cards' => $this->normalizeMetaCardsBlock($storedBlocks['meta_cards'] ?? null, $blockDefaults['blocks']['meta_cards']),
                'welcome' => $this->normalizeWelcomeBlock($storedBlocks['welcome'] ?? null, $blockDefaults['blocks']['welcome']),
                'countdown' => $this->normalizeCountdownBlock($storedBlocks['countdown'] ?? null, $blockDefaults['blocks']['countdown'], $event),
                'info_grid' => $this->normalizeInfoGridBlock($storedBlocks['info_grid'] ?? null, $blockDefaults['blocks']['info_grid']),
                'cta_list' => $this->normalizeCtaBlock($storedBlocks['cta_list'] ?? null, $blockDefaults['blocks']['cta_list']),
                'social_strip' => $this->normalizeSocialStripBlock($storedBlocks['social_strip'] ?? null, $blockDefaults['blocks']['social_strip']),
                'sponsor_strip' => $this->normalizeSponsorStripBlock($storedBlocks['sponsor_strip'] ?? null, $blockDefaults['blocks']['sponsor_strip']),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function layoutBlocks(string $layoutKey): array
    {
        return match ($layoutKey) {
            'hero-cards' => [
                'order' => ['hero', 'meta_cards', 'countdown', 'info_grid', 'welcome', 'cta_list', 'sponsor_strip'],
                'blocks' => [
                    'hero' => [
                        'enabled' => true,
                        'show_logo' => true,
                        'show_badge' => true,
                        'show_meta_cards' => false,
                        'height' => 'md',
                        'overlay_opacity' => 58,
                    ],
                    'meta_cards' => [
                        'enabled' => true,
                        'show_date' => true,
                        'show_location' => true,
                        'style' => 'glass',
                    ],
                    'welcome' => [
                        'enabled' => true,
                        'style' => 'card',
                    ],
                    'countdown' => [
                        'enabled' => false,
                        'style' => 'cards',
                        'target_mode' => 'event_start',
                        'target_at' => null,
                        'title' => 'Falta pouco',
                        'completed_message' => 'O evento ja comecou',
                        'hide_after_start' => false,
                    ],
                    'info_grid' => [
                        'enabled' => false,
                        'title' => 'Informacoes importantes',
                        'style' => 'cards',
                        'columns' => 2,
                        'items' => [],
                    ],
                    'cta_list' => [
                        'enabled' => true,
                        'style' => 'solid',
                        'size' => 'md',
                        'icon_position' => 'left',
                    ],
                    'social_strip' => [
                        'enabled' => true,
                        'style' => 'chips',
                        'size' => 'md',
                        'items' => [],
                    ],
                    'sponsor_strip' => [
                        'enabled' => false,
                        'title' => 'Patrocinadores',
                        'style' => 'cards',
                        'items' => [],
                    ],
                ],
            ],
            'minimal-center' => [
                'order' => ['hero', 'countdown', 'info_grid', 'cta_list', 'social_strip', 'sponsor_strip', 'welcome'],
                'blocks' => [
                    'hero' => [
                        'enabled' => true,
                        'show_logo' => true,
                        'show_badge' => false,
                        'show_meta_cards' => false,
                        'height' => 'sm',
                        'overlay_opacity' => 72,
                    ],
                    'meta_cards' => [
                        'enabled' => false,
                        'show_date' => true,
                        'show_location' => true,
                        'style' => 'minimal',
                    ],
                    'welcome' => [
                        'enabled' => true,
                        'style' => 'inline',
                    ],
                    'countdown' => [
                        'enabled' => false,
                        'style' => 'inline',
                        'target_mode' => 'event_start',
                        'target_at' => null,
                        'title' => 'Comeca em',
                        'completed_message' => 'Evento em andamento',
                        'hide_after_start' => false,
                    ],
                    'info_grid' => [
                        'enabled' => false,
                        'title' => 'Destaques do encontro',
                        'style' => 'cards',
                        'columns' => 2,
                        'items' => [],
                    ],
                    'cta_list' => [
                        'enabled' => true,
                        'style' => 'outline',
                        'size' => 'lg',
                        'icon_position' => 'top',
                    ],
                    'social_strip' => [
                        'enabled' => true,
                        'style' => 'icons',
                        'size' => 'sm',
                        'items' => [],
                    ],
                    'sponsor_strip' => [
                        'enabled' => false,
                        'title' => 'Patrocinadores',
                        'style' => 'logos',
                        'items' => [],
                    ],
                ],
            ],
            default => [
                'order' => ['hero', 'welcome', 'countdown', 'cta_list', 'social_strip', 'info_grid', 'sponsor_strip'],
                'blocks' => [
                    'hero' => [
                        'enabled' => true,
                        'show_logo' => true,
                        'show_badge' => true,
                        'show_meta_cards' => true,
                        'height' => 'lg',
                        'overlay_opacity' => 64,
                    ],
                    'meta_cards' => [
                        'enabled' => true,
                        'show_date' => true,
                        'show_location' => true,
                        'style' => 'glass',
                    ],
                    'welcome' => [
                        'enabled' => true,
                        'style' => 'bubble',
                    ],
                    'countdown' => [
                        'enabled' => false,
                        'style' => 'minimal',
                        'target_mode' => 'event_start',
                        'target_at' => null,
                        'title' => 'Contagem oficial',
                        'completed_message' => 'O evento ja comecou',
                        'hide_after_start' => false,
                    ],
                    'info_grid' => [
                        'enabled' => false,
                        'title' => 'Guia rapido do evento',
                        'style' => 'minimal',
                        'columns' => 2,
                        'items' => [],
                    ],
                    'cta_list' => [
                        'enabled' => true,
                        'style' => 'solid',
                        'size' => 'lg',
                        'icon_position' => 'left',
                    ],
                    'social_strip' => [
                        'enabled' => false,
                        'style' => 'icons',
                        'size' => 'md',
                        'items' => [],
                    ],
                    'sponsor_strip' => [
                        'enabled' => false,
                        'title' => 'Marcas apoiadoras',
                        'style' => 'logos',
                        'items' => [],
                    ],
                ],
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    private function themeTokens(string $themeKey, Event $event): array
    {
        return match ($themeKey) {
            'sunset' => [
                'page_background' => '#2c0f0f',
                'page_accent' => $event->secondary_color ?: '#f97316',
                'surface_background' => '#4b1d1d',
                'surface_border' => '#fb923c',
                'text_primary' => '#fff7ed',
                'text_secondary' => '#fed7aa',
                'hero_overlay_color' => '#1c0a0a',
            ],
            'wedding' => [
                'page_background' => '#fff7f5',
                'page_accent' => '#d97786',
                'surface_background' => '#ffffff',
                'surface_border' => '#f5d0d6',
                'text_primary' => '#4c0519',
                'text_secondary' => '#9f1239',
                'hero_overlay_color' => '#881337',
            ],
            'quince' => [
                'page_background' => '#3b0764',
                'page_accent' => '#ec4899',
                'surface_background' => '#581c87',
                'surface_border' => '#22d3ee',
                'text_primary' => '#faf5ff',
                'text_secondary' => '#e9d5ff',
                'hero_overlay_color' => '#2e1065',
            ],
            'corporate' => [
                'page_background' => '#ecfeff',
                'page_accent' => '#0f766e',
                'surface_background' => '#ffffff',
                'surface_border' => '#99f6e4',
                'text_primary' => '#134e4a',
                'text_secondary' => '#115e59',
                'hero_overlay_color' => '#134e4a',
            ],
            'pearl' => [
                'page_background' => '#f8fafc',
                'page_accent' => $event->primary_color ?: '#0f172a',
                'surface_background' => '#ffffff',
                'surface_border' => '#cbd5e1',
                'text_primary' => '#0f172a',
                'text_secondary' => '#475569',
                'hero_overlay_color' => '#0f172a',
            ],
            default => [
                'page_background' => '#020617',
                'page_accent' => $event->secondary_color ?: '#2563eb',
                'surface_background' => '#0f172a',
                'surface_border' => $event->secondary_color ?: '#1d4ed8',
                'text_primary' => '#ffffff',
                'text_secondary' => '#cbd5e1',
                'hero_overlay_color' => '#020617',
            ],
        };
    }

    /**
     * @param  mixed  $stored
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function normalizeHeroBlock(mixed $stored, array $defaults): array
    {
        $stored = is_array($stored) ? $stored : [];

        return [
            'enabled' => (bool) ($stored['enabled'] ?? $defaults['enabled']),
            'show_logo' => (bool) ($stored['show_logo'] ?? $defaults['show_logo']),
            'show_badge' => (bool) ($stored['show_badge'] ?? $defaults['show_badge']),
            'show_meta_cards' => (bool) ($stored['show_meta_cards'] ?? $defaults['show_meta_cards']),
            'height' => in_array($stored['height'] ?? null, ['sm', 'md', 'lg'], true)
                ? $stored['height']
                : $defaults['height'],
            'overlay_opacity' => $this->opacityOrDefault($stored['overlay_opacity'] ?? null, $defaults['overlay_opacity']),
        ];
    }

    /**
     * @param  mixed  $stored
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function normalizeMetaCardsBlock(mixed $stored, array $defaults): array
    {
        $stored = is_array($stored) ? $stored : [];

        return [
            'enabled' => (bool) ($stored['enabled'] ?? $defaults['enabled']),
            'show_date' => (bool) ($stored['show_date'] ?? $defaults['show_date']),
            'show_location' => (bool) ($stored['show_location'] ?? $defaults['show_location']),
            'style' => in_array($stored['style'] ?? null, ['glass', 'solid', 'minimal'], true)
                ? $stored['style']
                : $defaults['style'],
        ];
    }

    /**
     * @param  mixed  $stored
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function normalizeWelcomeBlock(mixed $stored, array $defaults): array
    {
        $stored = is_array($stored) ? $stored : [];

        return [
            'enabled' => (bool) ($stored['enabled'] ?? $defaults['enabled']),
            'style' => in_array($stored['style'] ?? null, ['card', 'inline', 'bubble'], true)
                ? $stored['style']
                : $defaults['style'],
        ];
    }

    /**
     * @param  mixed  $stored
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function normalizeCtaBlock(mixed $stored, array $defaults): array
    {
        $stored = is_array($stored) ? $stored : [];

        return [
            'enabled' => (bool) ($stored['enabled'] ?? $defaults['enabled']),
            'style' => in_array($stored['style'] ?? null, ['solid', 'outline', 'soft'], true)
                ? $stored['style']
                : $defaults['style'],
            'size' => in_array($stored['size'] ?? null, ['sm', 'md', 'lg'], true)
                ? $stored['size']
                : $defaults['size'],
            'icon_position' => in_array($stored['icon_position'] ?? null, ['left', 'top'], true)
                ? $stored['icon_position']
                : $defaults['icon_position'],
        ];
    }

    /**
     * @param  mixed  $stored
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function normalizeCountdownBlock(mixed $stored, array $defaults, Event $event): array
    {
        $stored = is_array($stored) ? $stored : [];
        $targetMode = in_array($stored['target_mode'] ?? null, ['event_start', 'custom'], true)
            ? $stored['target_mode']
            : $defaults['target_mode'];
        $eventTarget = $event->starts_at?->toIso8601String();
        $customTarget = is_string($stored['target_at'] ?? null) && trim($stored['target_at']) !== ''
            ? trim($stored['target_at'])
            : (is_string($defaults['target_at'] ?? null) && trim($defaults['target_at']) !== '' ? trim($defaults['target_at']) : null);
        $targetAt = $targetMode === 'event_start' ? $eventTarget : $customTarget;

        return [
            'enabled' => (bool) ($stored['enabled'] ?? $defaults['enabled']) && filled($targetAt),
            'style' => in_array($stored['style'] ?? null, ['cards', 'inline', 'minimal'], true)
                ? $stored['style']
                : $defaults['style'],
            'target_mode' => $targetMode,
            'target_at' => $targetAt,
            'title' => is_string($stored['title'] ?? null) && trim($stored['title']) !== ''
                ? trim($stored['title'])
                : $defaults['title'],
            'completed_message' => is_string($stored['completed_message'] ?? null) && trim($stored['completed_message']) !== ''
                ? trim($stored['completed_message'])
                : $defaults['completed_message'],
            'hide_after_start' => (bool) ($stored['hide_after_start'] ?? $defaults['hide_after_start']),
        ];
    }

    /**
     * @param  mixed  $stored
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function normalizeInfoGridBlock(mixed $stored, array $defaults): array
    {
        $stored = is_array($stored) ? $stored : [];
        $items = collect($stored['items'] ?? $defaults['items'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item) {
                return [
                    'id' => is_string($item['id'] ?? null) && trim($item['id']) !== '' ? trim($item['id']) : (string) str()->uuid(),
                    'title' => is_string($item['title'] ?? null) && trim($item['title']) !== '' ? trim($item['title']) : 'Informacao',
                    'value' => is_string($item['value'] ?? null) && trim($item['value']) !== '' ? trim($item['value']) : 'Atualize no editor',
                    'description' => is_string($item['description'] ?? null) && trim($item['description']) !== '' ? trim($item['description']) : null,
                    'icon' => $this->genericIconOrDefault($item['icon'] ?? null, 'sparkles'),
                    'is_visible' => (bool) ($item['is_visible'] ?? true),
                ];
            })
            ->values()
            ->all();

        return [
            'enabled' => (bool) ($stored['enabled'] ?? $defaults['enabled']),
            'title' => is_string($stored['title'] ?? null) && trim($stored['title']) !== ''
                ? trim($stored['title'])
                : $defaults['title'],
            'style' => in_array($stored['style'] ?? null, ['cards', 'minimal', 'highlight'], true)
                ? $stored['style']
                : $defaults['style'],
            'columns' => in_array((int) ($stored['columns'] ?? 0), [2, 3], true)
                ? (int) $stored['columns']
                : $defaults['columns'],
            'items' => $items,
        ];
    }

    /**
     * @param  mixed  $stored
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function normalizeSocialStripBlock(mixed $stored, array $defaults): array
    {
        $stored = is_array($stored) ? $stored : [];
        $items = collect($stored['items'] ?? $defaults['items'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item) {
                $provider = is_string($item['provider'] ?? null) ? $item['provider'] : 'website';
                $label = is_string($item['label'] ?? null) && trim($item['label']) !== ''
                    ? trim($item['label'])
                    : $this->defaultSocialLabel($provider);

                return [
                    'id' => is_string($item['id'] ?? null) && trim($item['id']) !== '' ? trim($item['id']) : (string) str()->uuid(),
                    'provider' => $this->socialProviderOrDefault($provider),
                    'label' => $label,
                    'href' => is_string($item['href'] ?? null) && trim($item['href']) !== '' ? trim($item['href']) : null,
                    'icon' => $this->socialIconOrDefault($item['icon'] ?? null, $provider),
                    'is_visible' => (bool) ($item['is_visible'] ?? true),
                    'opens_in_new_tab' => array_key_exists('opens_in_new_tab', $item) ? (bool) $item['opens_in_new_tab'] : true,
                ];
            })
            ->values()
            ->all();

        return [
            'enabled' => (bool) ($stored['enabled'] ?? $defaults['enabled']),
            'style' => in_array($stored['style'] ?? null, ['icons', 'chips', 'cards'], true)
                ? $stored['style']
                : $defaults['style'],
            'size' => in_array($stored['size'] ?? null, ['sm', 'md', 'lg'], true)
                ? $stored['size']
                : $defaults['size'],
            'items' => $items,
        ];
    }

    /**
     * @param  mixed  $stored
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function normalizeSponsorStripBlock(mixed $stored, array $defaults): array
    {
        $stored = is_array($stored) ? $stored : [];
        $items = collect($stored['items'] ?? $defaults['items'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item) {
                return [
                    'id' => is_string($item['id'] ?? null) && trim($item['id']) !== '' ? trim($item['id']) : (string) str()->uuid(),
                    'name' => is_string($item['name'] ?? null) && trim($item['name']) !== '' ? trim($item['name']) : 'Parceiro',
                    'subtitle' => is_string($item['subtitle'] ?? null) && trim($item['subtitle']) !== '' ? trim($item['subtitle']) : null,
                    'logo_path' => is_string($item['logo_path'] ?? null) && trim($item['logo_path']) !== '' ? trim($item['logo_path']) : null,
                    'href' => is_string($item['href'] ?? null) && trim($item['href']) !== '' ? trim($item['href']) : null,
                    'is_visible' => (bool) ($item['is_visible'] ?? true),
                    'opens_in_new_tab' => array_key_exists('opens_in_new_tab', $item) ? (bool) $item['opens_in_new_tab'] : true,
                ];
            })
            ->values()
            ->all();

        return [
            'enabled' => (bool) ($stored['enabled'] ?? $defaults['enabled']),
            'title' => is_string($stored['title'] ?? null) && trim($stored['title']) !== ''
                ? trim($stored['title'])
                : $defaults['title'],
            'style' => in_array($stored['style'] ?? null, ['logos', 'cards', 'compact'], true)
                ? $stored['style']
                : $defaults['style'],
            'items' => $items,
        ];
    }

    private function colorOrDefault(?string $value, string $default): string
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value)
            ? $value
            : $default;
    }

    private function opacityOrDefault(mixed $value, int $default): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        return max(0, min(90, (int) $value));
    }

    private function socialProviderOrDefault(?string $provider): string
    {
        return in_array($provider, ['instagram', 'whatsapp', 'tiktok', 'youtube', 'spotify', 'website', 'map', 'tickets'], true)
            ? $provider
            : 'website';
    }

    private function socialIconOrDefault(mixed $icon, string $provider): string
    {
        if (is_string($icon) && in_array($icon, $this->allowedIcons(), true)) {
            return $icon;
        }

        return match ($provider) {
            'instagram' => 'instagram',
            'whatsapp' => 'message-circle',
            'tiktok', 'spotify' => 'music',
            'youtube' => 'monitor',
            'map' => 'map-pin',
            'tickets' => 'ticket',
            default => 'link',
        };
    }

    private function genericIconOrDefault(mixed $icon, string $fallback): string
    {
        if (is_string($icon) && in_array($icon, $this->allowedIcons(), true)) {
            return $icon;
        }

        return $fallback;
    }

    private function defaultSocialLabel(string $provider): string
    {
        return match ($provider) {
            'instagram' => 'Instagram',
            'whatsapp' => 'WhatsApp',
            'tiktok' => 'TikTok',
            'youtube' => 'YouTube',
            'spotify' => 'Spotify',
            'map' => 'Mapa',
            'tickets' => 'Ingressos',
            default => 'Site oficial',
        };
    }

    /**
     * @return array<int, string>
     */
    private function allowedIcons(): array
    {
        return [
            'camera',
            'image',
            'monitor',
            'gamepad',
            'link',
            'calendar',
            'map-pin',
            'ticket',
            'music',
            'gift',
            'sparkles',
            'message-circle',
            'instagram',
        ];
    }
}
