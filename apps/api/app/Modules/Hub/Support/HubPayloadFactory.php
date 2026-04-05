<?php

namespace App\Modules\Hub\Support;

use App\Modules\Events\Http\Resources\EventResource;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Support\EventPublicLinksService;
use App\Modules\Hub\Models\EventHubSetting;
use App\Shared\Support\AssetUrlService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class HubPayloadFactory
{
    private const ICON_OPTIONS = [
        'camera' => 'Camera',
        'image' => 'Imagem',
        'monitor' => 'Monitor',
        'gamepad' => 'Gamepad',
        'link' => 'Link',
        'calendar' => 'Calendario',
        'map-pin' => 'Mapa',
        'ticket' => 'Ingresso',
        'music' => 'Musica',
        'gift' => 'Presente',
        'sparkles' => 'Destaque',
        'message-circle' => 'Mensagem',
        'instagram' => 'Instagram',
    ];

    private const PRESET_BUTTONS = [
        'upload' => [
            'label' => 'Enviar fotos',
            'icon' => 'camera',
            'description' => 'Receba fotos do evento pelo celular.',
        ],
        'gallery' => [
            'label' => 'Ver galeria',
            'icon' => 'image',
            'description' => 'Abra a galeria publica do evento.',
        ],
        'wall' => [
            'label' => 'Assistir wall',
            'icon' => 'monitor',
            'description' => 'Acompanhe o telao em tempo real.',
        ],
        'play' => [
            'label' => 'Jogar agora',
            'icon' => 'gamepad',
            'description' => 'Abra os minigames publicos do evento.',
        ],
    ];

    public function __construct(
        private readonly AssetUrlService $assets,
        private readonly EventPublicLinksService $eventLinks,
        private readonly HubBuilderPresetRegistry $builderPresets,
    ) {}

    public function ensureSettings(Event $event): EventHubSetting
    {
        $event->loadMissing(['modules', 'wallSettings']);

        return EventHubSetting::query()->firstOrCreate(
            ['event_id' => $event->id],
            $this->defaultsForEvent($event),
        );
    }

    public function admin(Event $event, EventHubSetting $settings): array
    {
        $event->loadMissing(['modules', 'wallSettings']);
        $links = $this->eventLinks->links($event)['links'];

        return [
            'event' => (new EventResource($event))->resolve(),
            'links' => [
                'hub_url' => $event->publicHubUrl(),
                'hub_api_url' => $event->publicHubApiUrl(),
                'gallery_url' => $links['gallery']['url'] ?? null,
                'upload_url' => $links['upload']['url'] ?? null,
                'wall_url' => $links['wall']['url'] ?? null,
                'play_url' => $links['play']['url'] ?? null,
            ],
            'settings' => [
                'id' => $settings->id,
                'event_id' => $settings->event_id,
                'is_enabled' => (bool) $settings->is_enabled,
                'headline' => $settings->headline ?: $event->title,
                'subheadline' => $settings->subheadline ?: $this->buildSubheadline($event),
                'welcome_text' => $settings->welcome_text ?: $event->description,
                'hero_image_path' => $settings->hero_image_path,
                'hero_image_url' => $this->resolveHeroUrl($settings, $event),
                'button_style' => $this->buttonStyle($event, $settings),
                'buttons' => $this->buttons($event, $settings),
                'builder_config' => $this->builderConfig($event, $settings),
                'sponsor' => $settings->sponsor_json ?? [],
                'extra_links' => $settings->extra_links_json ?? [],
                'created_at' => $settings->created_at?->toIso8601String(),
                'updated_at' => $settings->updated_at?->toIso8601String(),
            ],
            'options' => [
                'icons' => collect(self::ICON_OPTIONS)->map(
                    fn (string $label, string $value) => ['value' => $value, 'label' => $label]
                )->values()->all(),
                'preset_actions' => collect(self::PRESET_BUTTONS)->map(function (array $config, string $key) use ($links) {
                    return [
                        'preset_key' => $key,
                        'label' => $config['label'],
                        'icon' => $config['icon'],
                        'description' => $config['description'],
                        'is_available' => (bool) ($links[$key]['enabled'] ?? false),
                        'resolved_url' => $links[$key]['url'] ?? null,
                    ];
                })->values()->all(),
            ],
        ];
    }

    public function public(Event $event, EventHubSetting $settings): array
    {
        $event->loadMissing(['modules', 'wallSettings']);
        $builderConfig = $this->builderConfig($event, $settings);

        return [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'location_name' => $event->location_name,
                'description' => $event->description,
                'cover_image_path' => $event->cover_image_path,
                'cover_image_url' => $this->assets->toPublicUrl($event->cover_image_path),
                'logo_path' => $event->logo_path,
                'logo_url' => $this->assets->toPublicUrl($event->logo_path),
                'primary_color' => $event->primary_color,
                'secondary_color' => $event->secondary_color,
                'public_url' => $event->publicHubUrl(),
            ],
            'hub' => [
                'headline' => $settings->headline ?: $event->title,
                'subheadline' => $settings->subheadline ?: $this->buildSubheadline($event),
                'welcome_text' => $settings->welcome_text ?: $event->description,
                'hero_image_url' => $this->resolveHeroUrl($settings, $event),
                'button_style' => $this->buttonStyle($event, $settings),
                'builder_config' => $builderConfig,
                'buttons' => collect($this->buttons($event, $settings))
                    ->filter(fn (array $button) => $button['is_visible'] && $button['is_available'] && $button['resolved_url'])
                    ->values()
                    ->all(),
            ],
        ];
    }

    public function defaultsForEvent(Event $event): array
    {
        return [
            'is_enabled' => $event->isModuleEnabled('hub'),
            'headline' => $event->title,
            'subheadline' => $this->buildSubheadline($event),
            'welcome_text' => $event->description,
            'hero_image_path' => $event->cover_image_path,
            'show_gallery_button' => $event->isModuleEnabled('live'),
            'show_upload_button' => $event->isModuleEnabled('live'),
            'show_wall_button' => $event->isModuleEnabled('wall'),
            'show_play_button' => $event->isModuleEnabled('play'),
            'button_style_json' => [
                'background_color' => $event->primary_color ?: '#0f172a',
                'text_color' => '#ffffff',
                'outline_color' => $event->secondary_color ?: '#e2e8f0',
            ],
            'buttons_json' => $this->defaultButtons($event),
            'builder_config_json' => $this->builderPresets->defaultsForEvent($event),
        ];
    }

    public function presetVisibility(array $buttons): array
    {
        $visible = collect($buttons)
            ->filter(fn (array $button) => ($button['type'] ?? 'custom') === 'preset')
            ->mapWithKeys(fn (array $button) => [
                (string) ($button['preset_key'] ?? '') => (bool) ($button['is_visible'] ?? true),
            ]);

        // Absent presets default to false — if the user didn't include them, they are not shown
        return [
            'show_gallery_button' => (bool) $visible->get('gallery', false),
            'show_upload_button'  => (bool) $visible->get('upload', false),
            'show_wall_button'    => (bool) $visible->get('wall', false),
            'show_play_button'    => (bool) $visible->get('play', false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buttons(Event $event, EventHubSetting $settings): array
    {
        $links = $this->eventLinks->links($event)['links'];
        $buttonStyle = $this->buttonStyle($event, $settings);
        $storedButtons = $this->normalizedStoredButtons($settings);

        return collect($storedButtons)
            ->map(function (array $button, int $index) use ($links, $buttonStyle) {
                $presetKey = $button['preset_key'];
                $isPreset = $button['type'] === 'preset' && $presetKey !== null;
                $resolvedUrl = $isPreset
                    ? ($links[$presetKey]['url'] ?? null)
                    : ($button['href'] ?? null);
                $isAvailable = $isPreset
                    ? (bool) ($links[$presetKey]['enabled'] ?? false)
                    : filled($button['href']);

                return [
                    'id' => $button['id'] ?: "button-{$index}",
                    'type' => $button['type'],
                    'preset_key' => $presetKey,
                    'label' => $button['label'],
                    'icon' => $button['icon'],
                    'href' => $button['href'],
                    'resolved_url' => $resolvedUrl,
                    'is_visible' => (bool) $button['is_visible'],
                    'is_available' => $isAvailable,
                    'opens_in_new_tab' => (bool) $button['opens_in_new_tab'],
                    'background_color' => $button['background_color'] ?: $buttonStyle['background_color'],
                    'text_color' => $button['text_color'] ?: $buttonStyle['text_color'],
                    'outline_color' => $button['outline_color'] ?: $buttonStyle['outline_color'],
                    'sort_order' => $index + 1,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function clickTargets(Event $event, EventHubSetting $settings): array
    {
        $builderConfig = $this->builderConfig($event, $settings);

        $buttons = collect($this->buttons($event, $settings))
            ->when(
                ! (bool) data_get($builderConfig, 'blocks.cta_list.enabled', true),
                fn ($collection) => $collection->take(0),
            )
            ->filter(fn (array $button) => (bool) ($button['is_visible'] ?? false) && (bool) ($button['is_available'] ?? false) && filled($button['resolved_url'] ?? null))
            ->values();

        $socials = collect($builderConfig['blocks']['social_strip']['items'] ?? [])
            ->when(
                ! (bool) data_get($builderConfig, 'blocks.social_strip.enabled', false),
                fn ($collection) => $collection->take(0),
            )
            ->filter(fn ($item) => is_array($item) && ($item['is_visible'] ?? false) && filled($item['href'] ?? null))
            ->map(function (array $item) {
                return [
                    'id' => (string) ($item['id'] ?? ''),
                    'type' => 'social',
                    'preset_key' => null,
                    'label' => (string) ($item['label'] ?? 'Rede social'),
                    'icon' => (string) ($item['icon'] ?? 'link'),
                    'resolved_url' => (string) ($item['href'] ?? ''),
                    'is_visible' => (bool) ($item['is_visible'] ?? true),
                    'opens_in_new_tab' => (bool) ($item['opens_in_new_tab'] ?? true),
                ];
            });

        $sponsors = collect($builderConfig['blocks']['sponsor_strip']['items'] ?? [])
            ->when(
                ! (bool) data_get($builderConfig, 'blocks.sponsor_strip.enabled', false),
                fn ($collection) => $collection->take(0),
            )
            ->filter(fn ($item) => is_array($item) && ($item['is_visible'] ?? false) && filled($item['href'] ?? null))
            ->map(function (array $item) {
                return [
                    'id' => (string) ($item['id'] ?? ''),
                    'type' => 'sponsor',
                    'preset_key' => null,
                    'label' => (string) ($item['name'] ?? 'Patrocinador'),
                    'icon' => 'gift',
                    'resolved_url' => (string) ($item['href'] ?? ''),
                    'is_visible' => (bool) ($item['is_visible'] ?? true),
                    'opens_in_new_tab' => (bool) ($item['opens_in_new_tab'] ?? true),
                ];
            });

        return [
            ...$buttons->all(),
            ...$socials->values()->all(),
            ...$sponsors->values()->all(),
        ];
    }

    private function buttonStyle(Event $event, EventHubSetting $settings): array
    {
        $stored = is_array($settings->button_style_json) ? $settings->button_style_json : [];

        return [
            'background_color' => $stored['background_color'] ?? $event->primary_color ?? '#0f172a',
            'text_color' => $stored['text_color'] ?? '#ffffff',
            'outline_color' => $stored['outline_color'] ?? $event->secondary_color ?? '#e2e8f0',
        ];
    }

    private function resolveHeroUrl(EventHubSetting $settings, Event $event): ?string
    {
        return $this->assets->toPublicUrl($settings->hero_image_path)
            ?: $this->assets->toPublicUrl($event->cover_image_path);
    }

    private function builderConfig(Event $event, EventHubSetting $settings): array
    {
        return $this->builderPresets->normalize($settings->builder_config_json, $event);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizedStoredButtons(EventHubSetting $settings): array
    {
        $stored = is_array($settings->buttons_json) ? $settings->buttons_json : [];

        if ($stored === []) {
            $stored = $this->legacyButtons($settings);
        }

        if (is_array($settings->extra_links_json)) {
            foreach ($settings->extra_links_json as $legacyLink) {
                if (! is_array($legacyLink)) {
                    continue;
                }

                $stored[] = [
                    'id' => (string) ($legacyLink['id'] ?? Str::uuid()),
                    'type' => 'custom',
                    'preset_key' => null,
                    'label' => $legacyLink['label'] ?? 'Novo link',
                    'icon' => $legacyLink['icon'] ?? 'link',
                    'href' => $legacyLink['url'] ?? null,
                    'is_visible' => (bool) ($legacyLink['is_visible'] ?? true),
                    'opens_in_new_tab' => (bool) ($legacyLink['opens_in_new_tab'] ?? true),
                    'background_color' => $legacyLink['background_color'] ?? null,
                    'text_color' => $legacyLink['text_color'] ?? null,
                    'outline_color' => $legacyLink['outline_color'] ?? null,
                ];
            }
        }

        return collect($stored)
            ->filter(fn ($button) => is_array($button))
            ->map(function (array $button) {
                $presetKey = $button['preset_key'] ?? null;
                $isPreset = ($button['type'] ?? 'preset') === 'preset' && $presetKey !== null;
                $default = $isPreset ? (self::PRESET_BUTTONS[$presetKey] ?? null) : null;
                $icon = (string) ($button['icon'] ?? ($default['icon'] ?? 'link'));

                return [
                    'id' => (string) ($button['id'] ?? Str::uuid()),
                    'type' => $isPreset ? 'preset' : 'custom',
                    'preset_key' => $isPreset ? $presetKey : null,
                    'label' => (string) ($button['label'] ?? ($default['label'] ?? 'Novo link')),
                    'icon' => array_key_exists($icon, self::ICON_OPTIONS) ? $icon : 'link',
                    'href' => $button['href'] ?? null,
                    'is_visible' => (bool) ($button['is_visible'] ?? true),
                    'opens_in_new_tab' => (bool) ($button['opens_in_new_tab'] ?? true),
                    'background_color' => $button['background_color'] ?? null,
                    'text_color' => $button['text_color'] ?? null,
                    'outline_color' => $button['outline_color'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultButtons(Event $event): array
    {
        return [
            [
                'id' => 'preset-upload',
                'type' => 'preset',
                'preset_key' => 'upload',
                'label' => self::PRESET_BUTTONS['upload']['label'],
                'icon' => self::PRESET_BUTTONS['upload']['icon'],
                'is_visible' => $event->isModuleEnabled('live'),
                'opens_in_new_tab' => false,
            ],
            [
                'id' => 'preset-gallery',
                'type' => 'preset',
                'preset_key' => 'gallery',
                'label' => self::PRESET_BUTTONS['gallery']['label'],
                'icon' => self::PRESET_BUTTONS['gallery']['icon'],
                'is_visible' => $event->isModuleEnabled('live'),
                'opens_in_new_tab' => false,
            ],
            [
                'id' => 'preset-wall',
                'type' => 'preset',
                'preset_key' => 'wall',
                'label' => self::PRESET_BUTTONS['wall']['label'],
                'icon' => self::PRESET_BUTTONS['wall']['icon'],
                'is_visible' => $event->isModuleEnabled('wall'),
                'opens_in_new_tab' => true,
            ],
            [
                'id' => 'preset-play',
                'type' => 'preset',
                'preset_key' => 'play',
                'label' => self::PRESET_BUTTONS['play']['label'],
                'icon' => self::PRESET_BUTTONS['play']['icon'],
                'is_visible' => $event->isModuleEnabled('play'),
                'opens_in_new_tab' => false,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function legacyButtons(EventHubSetting $settings): array
    {
        return [
            [
                'id' => 'preset-upload',
                'type' => 'preset',
                'preset_key' => 'upload',
                'label' => self::PRESET_BUTTONS['upload']['label'],
                'icon' => self::PRESET_BUTTONS['upload']['icon'],
                'is_visible' => (bool) $settings->show_upload_button,
                'opens_in_new_tab' => false,
            ],
            [
                'id' => 'preset-gallery',
                'type' => 'preset',
                'preset_key' => 'gallery',
                'label' => self::PRESET_BUTTONS['gallery']['label'],
                'icon' => self::PRESET_BUTTONS['gallery']['icon'],
                'is_visible' => (bool) $settings->show_gallery_button,
                'opens_in_new_tab' => false,
            ],
            [
                'id' => 'preset-wall',
                'type' => 'preset',
                'preset_key' => 'wall',
                'label' => self::PRESET_BUTTONS['wall']['label'],
                'icon' => self::PRESET_BUTTONS['wall']['icon'],
                'is_visible' => (bool) $settings->show_wall_button,
                'opens_in_new_tab' => true,
            ],
            [
                'id' => 'preset-play',
                'type' => 'preset',
                'preset_key' => 'play',
                'label' => self::PRESET_BUTTONS['play']['label'],
                'icon' => self::PRESET_BUTTONS['play']['icon'],
                'is_visible' => (bool) $settings->show_play_button,
                'opens_in_new_tab' => false,
            ],
        ];
    }

    private function buildSubheadline(Event $event): ?string
    {
        $parts = [];

        if ($event->starts_at instanceof Carbon) {
            $parts[] = $event->starts_at->locale('pt_BR')->translatedFormat('d \d\e F \d\e Y');
        }

        if (filled($event->location_name)) {
            $parts[] = $event->location_name;
        }

        return $parts !== [] ? implode(' - ', $parts) : null;
    }
}
