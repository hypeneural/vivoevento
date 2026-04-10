<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\Hub\Models\EventHubSetting;
use App\Modules\Wall\Models\EventWallSetting;

it('returns the admin hub settings payload with normalized buttons', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento Hub Admin',
        'primary_color' => '#111827',
        'secondary_color' => '#f97316',
    ]);

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'live', 'is_enabled' => true]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'wall', 'is_enabled' => true]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'play', 'is_enabled' => false]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'hub', 'is_enabled' => true]);
    EventWallSetting::factory()->create(['event_id' => $event->id, 'is_enabled' => true]);

    EventHubSetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
        'headline' => 'Hub oficial',
        'welcome_text' => 'Bem-vindo',
        'button_style_json' => [
            'background_color' => '#111827',
            'text_color' => '#ffffff',
            'outline_color' => '#f97316',
        ],
        'buttons_json' => [
            [
                'id' => 'preset-upload',
                'type' => 'preset',
                'preset_key' => 'upload',
                'label' => 'Mandar fotos',
                'icon' => 'camera',
                'is_visible' => true,
                'opens_in_new_tab' => false,
            ],
            [
                'id' => 'custom-mapa',
                'type' => 'custom',
                'label' => 'Como chegar',
                'icon' => 'map-pin',
                'href' => 'https://maps.example.com',
                'is_visible' => true,
                'opens_in_new_tab' => true,
                'background_color' => '#ffffff',
                'text_color' => '#111827',
            ],
        ],
    ]);

    $response = $this->apiGet("/events/{$event->id}/hub/settings");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.settings.headline', 'Hub oficial')
        ->assertJsonPath('data.settings.builder_config.layout_key', 'classic-cover')
        ->assertJsonPath('data.settings.builder_config.blocks.countdown.target_mode', 'event_start')
        ->assertJsonPath('data.settings.buttons.0.label', 'Mandar fotos')
        ->assertJsonPath('data.settings.buttons.0.resolved_url', $event->publicUploadUrl())
        ->assertJsonPath('data.settings.buttons.1.label', 'Como chegar')
        ->assertJsonPath('data.settings.buttons.1.resolved_url', 'https://maps.example.com');
});

it('updates hub settings using the structured editor payload', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento Hub Update',
    ]);

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'live', 'is_enabled' => true]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'wall', 'is_enabled' => false]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'play', 'is_enabled' => true]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'hub', 'is_enabled' => true]);

    $response = $this->apiPatch("/events/{$event->id}/hub/settings", [
        'is_enabled' => true,
        'headline' => 'Novo headline',
        'subheadline' => 'Subheadline',
        'welcome_text' => 'Texto de apoio',
        'hero_image_path' => 'https://example.com/hub.jpg',
        'button_style' => [
            'background_color' => '#111827',
            'text_color' => '#ffffff',
            'outline_color' => '#f97316',
        ],
        'builder_config' => [
            'version' => 1,
            'layout_key' => 'hero-cards',
            'theme_key' => 'sunset',
            'theme_tokens' => [
                'page_background' => '#2c0f0f',
                'page_accent' => '#f97316',
                'surface_background' => '#4b1d1d',
                'surface_border' => '#fb923c',
                'text_primary' => '#fff7ed',
                'text_secondary' => '#fed7aa',
                'hero_overlay_color' => '#1c0a0a',
            ],
            'block_order' => ['hero', 'meta_cards', 'countdown', 'info_grid', 'welcome', 'social_strip', 'cta_list', 'sponsor_strip'],
            'blocks' => [
                'hero' => [
                    'enabled' => true,
                    'show_logo' => true,
                    'show_badge' => true,
                    'show_meta_cards' => false,
                    'height' => 'md',
                    'overlay_opacity' => 52,
                ],
                'meta_cards' => [
                    'enabled' => true,
                    'show_date' => true,
                    'show_location' => false,
                    'style' => 'solid',
                ],
                'welcome' => [
                    'enabled' => true,
                    'style' => 'card',
                ],
                'countdown' => [
                    'enabled' => true,
                    'style' => 'cards',
                    'target_mode' => 'event_start',
                    'target_at' => now()->addDays(10)->toIso8601String(),
                    'title' => 'Falta pouco',
                    'completed_message' => 'Evento em andamento',
                    'hide_after_start' => false,
                ],
                'info_grid' => [
                    'enabled' => true,
                    'title' => 'Informacoes importantes',
                    'style' => 'highlight',
                    'columns' => 2,
                    'items' => [
                        [
                            'id' => 'info-dress-code',
                            'title' => 'Dress code',
                            'value' => 'Esporte fino',
                            'description' => 'Tons claros recomendados.',
                            'icon' => 'sparkles',
                            'is_visible' => true,
                        ],
                    ],
                ],
                'social_strip' => [
                    'enabled' => true,
                    'style' => 'chips',
                    'size' => 'md',
                    'items' => [
                        [
                            'id' => 'social-instagram',
                            'provider' => 'instagram',
                            'label' => 'Instagram oficial',
                            'href' => 'https://instagram.com/evento',
                            'icon' => 'instagram',
                            'is_visible' => true,
                            'opens_in_new_tab' => true,
                        ],
                    ],
                ],
                'sponsor_strip' => [
                    'enabled' => true,
                    'title' => 'Marcas parceiras',
                    'style' => 'cards',
                    'items' => [
                        [
                            'id' => 'sponsor-master',
                            'name' => 'Marca One',
                            'subtitle' => 'Patrocinador master',
                            'logo_path' => 'hub/sponsors/marca-one.png',
                            'href' => 'https://example.com/sponsor',
                            'is_visible' => true,
                            'opens_in_new_tab' => true,
                        ],
                    ],
                ],
                'cta_list' => [
                    'enabled' => true,
                    'style' => 'outline',
                    'size' => 'md',
                    'icon_position' => 'left',
                ],
            ],
        ],
        'buttons' => [
            [
                'id' => 'preset-gallery',
                'type' => 'preset',
                'preset_key' => 'gallery',
                'label' => 'Abrir fotos',
                'icon' => 'image',
                'is_visible' => true,
                'opens_in_new_tab' => false,
            ],
            [
                'id' => 'custom-instagram',
                'type' => 'custom',
                'preset_key' => null,
                'label' => 'Instagram',
                'icon' => 'instagram',
                'href' => 'https://instagram.com/evento',
                'is_visible' => true,
                'opens_in_new_tab' => true,
                'background_color' => '#ffffff',
                'text_color' => '#111827',
                'outline_color' => '#e2e8f0',
            ],
        ],
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.settings.buttons.0.label', 'Abrir fotos')
        ->assertJsonPath('data.settings.builder_config.layout_key', 'hero-cards')
        ->assertJsonPath('data.settings.builder_config.blocks.meta_cards.show_location', false)
        ->assertJsonPath('data.settings.builder_config.blocks.countdown.enabled', true)
        ->assertJsonPath('data.settings.builder_config.blocks.countdown.style', 'cards')
        ->assertJsonPath('data.settings.builder_config.blocks.countdown.title', 'Falta pouco')
        ->assertJsonPath('data.settings.builder_config.blocks.info_grid.enabled', true)
        ->assertJsonPath('data.settings.builder_config.blocks.info_grid.items.0.title', 'Dress code')
        ->assertJsonPath('data.settings.builder_config.blocks.social_strip.items.0.label', 'Instagram oficial')
        ->assertJsonPath('data.settings.builder_config.blocks.sponsor_strip.enabled', true)
        ->assertJsonPath('data.settings.builder_config.blocks.sponsor_strip.items.0.name', 'Marca One')
        ->assertJsonPath('data.settings.buttons.1.icon', 'instagram');

    $this->assertDatabaseHas('event_hub_settings', [
        'event_id' => $event->id,
        'headline' => 'Novo headline',
        'show_gallery_button' => true,
        'show_upload_button' => false,
        'show_play_button' => false,
    ]);
});

it('returns the public hub payload with visible actionable buttons only', function () {
    $event = Event::factory()->active()->create([
        'title' => 'Evento Publico',
        'primary_color' => '#0f172a',
        'secondary_color' => '#f59e0b',
        'starts_at' => now()->addDays(3),
    ]);

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'live', 'is_enabled' => true]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'wall', 'is_enabled' => false]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'play', 'is_enabled' => false]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'hub', 'is_enabled' => true]);
    EventFaceSearchSetting::factory()->publicSelfieSearch()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'routing_policy' => 'aws_primary_local_fallback',
        'aws_collection_id' => "eventovivo-face-search-event-{$event->id}",
    ]);

    EventHubSetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
        'buttons_json' => [
            [
                'id' => 'preset-upload',
                'type' => 'preset',
                'preset_key' => 'upload',
                'label' => 'Enviar agora',
                'icon' => 'camera',
                'is_visible' => true,
                'opens_in_new_tab' => false,
            ],
            [
                'id' => 'custom-site',
                'type' => 'custom',
                'preset_key' => null,
                'label' => 'Site do casal',
                'icon' => 'link',
                'href' => 'https://example.com',
                'is_visible' => true,
                'opens_in_new_tab' => true,
            ],
            [
                'id' => 'preset-wall',
                'type' => 'preset',
                'preset_key' => 'wall',
                'label' => 'Ver wall',
                'icon' => 'monitor',
                'is_visible' => true,
                'opens_in_new_tab' => true,
            ],
        ],
    ]);

    $response = $this->getJson("/api/v1/public/events/{$event->slug}/hub");

    $response->assertOk()
        ->assertJsonPath('data.event.title', 'Evento Publico')
        ->assertJsonPath('data.hub.builder_config.layout_key', 'classic-cover')
        ->assertJsonPath('data.hub.buttons.0.label', 'Enviar agora')
        ->assertJsonPath('data.hub.buttons.1.label', 'Site do casal')
        ->assertJsonPath('data.face_search.public_search_enabled', true)
        ->assertJsonPath('data.face_search.find_me_url', $event->publicFindMeUrl());

    expect($response->json('data.hub.buttons'))->toHaveCount(2);
});

it('returns the public hub payload with countdown resolved from the event start date', function () {
    $event = Event::factory()->active()->create([
        'title' => 'Evento com Countdown',
        'starts_at' => now()->addDays(5),
    ]);

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'hub', 'is_enabled' => true]);

    EventHubSetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
        'builder_config_json' => [
            'version' => 1,
            'layout_key' => 'classic-cover',
            'theme_key' => 'midnight',
            'theme_tokens' => [
                'page_background' => '#020617',
                'page_accent' => '#2563eb',
                'surface_background' => '#0f172a',
                'surface_border' => '#1d4ed8',
                'text_primary' => '#ffffff',
                'text_secondary' => '#cbd5e1',
                'hero_overlay_color' => '#020617',
            ],
            'block_order' => ['hero', 'welcome', 'countdown', 'cta_list'],
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
                    'enabled' => true,
                    'style' => 'minimal',
                    'target_mode' => 'event_start',
                    'target_at' => null,
                    'title' => 'Contagem oficial',
                    'completed_message' => 'O evento ja comecou',
                    'hide_after_start' => false,
                ],
                'cta_list' => [
                    'enabled' => true,
                    'style' => 'solid',
                    'size' => 'md',
                    'icon_position' => 'left',
                ],
                'social_strip' => [
                    'enabled' => false,
                    'style' => 'icons',
                    'size' => 'md',
                    'items' => [],
                ],
            ],
        ],
    ]);

    $response = $this->getJson("/api/v1/public/events/{$event->slug}/hub");

    $response->assertOk()
        ->assertJsonPath('data.hub.builder_config.blocks.countdown.enabled', true)
        ->assertJsonPath('data.hub.builder_config.blocks.countdown.target_mode', 'event_start')
        ->assertJsonPath('data.hub.builder_config.blocks.countdown.target_at', $event->starts_at?->toIso8601String())
        ->assertJsonPath('data.hub.builder_config.blocks.countdown.title', 'Contagem oficial');
});

it('returns the public hub payload with social strip items inside builder config', function () {
    $event = Event::factory()->active()->create([
        'title' => 'Evento Social',
    ]);

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'live', 'is_enabled' => true]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'hub', 'is_enabled' => true]);

    EventHubSetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
        'builder_config_json' => [
            'version' => 1,
            'layout_key' => 'hero-cards',
            'theme_key' => 'sunset',
            'theme_tokens' => [
                'page_background' => '#2c0f0f',
                'page_accent' => '#f97316',
                'surface_background' => '#4b1d1d',
                'surface_border' => '#fb923c',
                'text_primary' => '#fff7ed',
                'text_secondary' => '#fed7aa',
                'hero_overlay_color' => '#1c0a0a',
            ],
            'block_order' => ['hero', 'meta_cards', 'welcome', 'social_strip', 'cta_list'],
            'blocks' => [
                'hero' => [
                    'enabled' => true,
                    'show_logo' => true,
                    'show_badge' => true,
                    'show_meta_cards' => true,
                    'height' => 'md',
                    'overlay_opacity' => 52,
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
                'social_strip' => [
                    'enabled' => true,
                    'style' => 'cards',
                    'size' => 'md',
                    'items' => [
                        [
                            'id' => 'social-whatsapp',
                            'provider' => 'whatsapp',
                            'label' => 'Lista VIP',
                            'href' => 'https://wa.me/5511999999999',
                            'icon' => 'message-circle',
                            'is_visible' => true,
                            'opens_in_new_tab' => true,
                        ],
                    ],
                ],
                'cta_list' => [
                    'enabled' => true,
                    'style' => 'solid',
                    'size' => 'md',
                    'icon_position' => 'left',
                ],
            ],
        ],
    ]);

    $response = $this->getJson("/api/v1/public/events/{$event->slug}/hub");

    $response->assertOk()
        ->assertJsonPath('data.hub.builder_config.blocks.social_strip.enabled', true)
        ->assertJsonPath('data.hub.builder_config.blocks.social_strip.items.0.id', 'social-whatsapp')
        ->assertJsonPath('data.hub.builder_config.blocks.social_strip.items.0.provider', 'whatsapp')
        ->assertJsonPath('data.hub.builder_config.blocks.social_strip.items.0.label', 'Lista VIP');
});

it('returns the public hub payload with info grid and sponsor strip items inside builder config', function () {
    $event = Event::factory()->active()->create([
        'title' => 'Evento com blocos ricos',
    ]);

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'hub', 'is_enabled' => true]);

    EventHubSetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
        'builder_config_json' => [
            'version' => 1,
            'layout_key' => 'hero-cards',
            'theme_key' => 'sunset',
            'theme_tokens' => [
                'page_background' => '#2c0f0f',
                'page_accent' => '#f97316',
                'surface_background' => '#4b1d1d',
                'surface_border' => '#fb923c',
                'text_primary' => '#fff7ed',
                'text_secondary' => '#fed7aa',
                'hero_overlay_color' => '#1c0a0a',
            ],
            'block_order' => ['hero', 'countdown', 'info_grid', 'cta_list', 'sponsor_strip'],
            'blocks' => [
                'hero' => [
                    'enabled' => true,
                    'show_logo' => true,
                    'show_badge' => true,
                    'show_meta_cards' => false,
                    'height' => 'md',
                    'overlay_opacity' => 52,
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
                    'enabled' => true,
                    'title' => 'Guia rapido',
                    'style' => 'cards',
                    'columns' => 2,
                    'items' => [
                        [
                            'id' => 'info-hashtag',
                            'title' => 'Hashtag',
                            'value' => '#EventoVivo',
                            'description' => 'Use nas fotos e videos.',
                            'icon' => 'instagram',
                            'is_visible' => true,
                        ],
                    ],
                ],
                'cta_list' => [
                    'enabled' => true,
                    'style' => 'solid',
                    'size' => 'md',
                    'icon_position' => 'left',
                ],
                'social_strip' => [
                    'enabled' => false,
                    'style' => 'icons',
                    'size' => 'sm',
                    'items' => [],
                ],
                'sponsor_strip' => [
                    'enabled' => true,
                    'title' => 'Patrocinadores',
                    'style' => 'logos',
                    'items' => [
                        [
                            'id' => 'sponsor-alpha',
                            'name' => 'Alpha',
                            'subtitle' => 'Patrocinador oficial',
                            'logo_path' => 'hub/sponsors/alpha.png',
                            'href' => 'https://example.com/alpha',
                            'is_visible' => true,
                            'opens_in_new_tab' => true,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $response = $this->getJson("/api/v1/public/events/{$event->slug}/hub");

    $response->assertOk()
        ->assertJsonPath('data.hub.builder_config.blocks.info_grid.enabled', true)
        ->assertJsonPath('data.hub.builder_config.blocks.info_grid.items.0.title', 'Hashtag')
        ->assertJsonPath('data.hub.builder_config.blocks.sponsor_strip.enabled', true)
        ->assertJsonPath('data.hub.builder_config.blocks.sponsor_strip.items.0.name', 'Alpha')
        ->assertJsonPath('data.hub.builder_config.blocks.sponsor_strip.items.0.href', 'https://example.com/alpha');
});
