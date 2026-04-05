<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\Hub\Models\HubPreset;

it('lists hub presets from the current organization only', function () {
    [$user, $organization] = $this->actingAsOwner();

    $otherOrganization = \App\Modules\Organizations\Models\Organization::factory()->create();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento principal',
    ]);

    HubPreset::factory()->create([
        'organization_id' => $organization->id,
        'source_event_id' => $event->id,
        'name' => 'Modelo casamento',
    ]);

    HubPreset::factory()->create([
        'organization_id' => $otherOrganization->id,
        'name' => 'Modelo externo',
    ]);

    $response = $this->apiGet('/hub/presets');

    $this->assertApiSuccess($response);

    expect($response->json('data'))->toHaveCount(1);
    $response->assertJsonPath('data.0.name', 'Modelo casamento');
});

it('stores a reusable hub preset for the current organization', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento para modelo',
    ]);

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'hub', 'is_enabled' => true]);

    $response = $this->apiPost('/hub/presets', [
        'event_id' => $event->id,
        'name' => 'Modelo corporativo clean',
        'description' => 'Base reutilizavel para eventos institucionais.',
        'button_style' => [
            'background_color' => '#0f172a',
            'text_color' => '#ffffff',
            'outline_color' => '#cbd5e1',
        ],
        'builder_config' => [
            'version' => 1,
            'layout_key' => 'minimal-center',
            'theme_key' => 'pearl',
            'theme_tokens' => [
                'page_background' => '#f8fafc',
                'page_accent' => '#0f172a',
                'surface_background' => '#ffffff',
                'surface_border' => '#cbd5e1',
                'text_primary' => '#0f172a',
                'text_secondary' => '#475569',
                'hero_overlay_color' => '#0f172a',
            ],
            'block_order' => ['hero', 'countdown', 'cta_list', 'welcome'],
            'blocks' => [
                'hero' => [
                    'enabled' => true,
                    'show_logo' => true,
                    'show_badge' => false,
                    'show_meta_cards' => false,
                    'height' => 'sm',
                    'overlay_opacity' => 70,
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
                    'enabled' => true,
                    'style' => 'inline',
                    'target_mode' => 'event_start',
                    'target_at' => now()->addDays(5)->toIso8601String(),
                    'title' => 'Comeca em',
                    'completed_message' => 'Evento em andamento',
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
                    'style' => 'outline',
                    'size' => 'lg',
                    'icon_position' => 'top',
                ],
                'social_strip' => [
                    'enabled' => false,
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
        'buttons' => [
            [
                'id' => 'preset-gallery',
                'type' => 'preset',
                'preset_key' => 'gallery',
                'label' => 'Ver fotos',
                'icon' => 'image',
                'href' => null,
                'is_visible' => true,
                'opens_in_new_tab' => false,
                'background_color' => null,
                'text_color' => null,
                'outline_color' => null,
            ],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Modelo corporativo clean')
        ->assertJsonPath('data.theme_key', 'pearl')
        ->assertJsonPath('data.layout_key', 'minimal-center')
        ->assertJsonPath('data.source_event.title', 'Evento para modelo')
        ->assertJsonPath('data.payload.builder_config.theme_key', 'pearl');

    $this->assertDatabaseHas('hub_presets', [
        'organization_id' => $organization->id,
        'source_event_id' => $event->id,
        'name' => 'Modelo corporativo clean',
        'theme_key' => 'pearl',
    ]);
});
