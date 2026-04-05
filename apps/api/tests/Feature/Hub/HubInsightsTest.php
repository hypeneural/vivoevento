<?php

use App\Modules\Analytics\Models\AnalyticsEvent;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\Hub\Models\EventHubSetting;
use Carbon\CarbonImmutable;

function enableHubModule(Event $event, string $moduleKey): void
{
    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => $moduleKey,
        'is_enabled' => true,
    ]);
}

it('tracks public hub button clicks using the current hub payload', function () {
    $event = Event::factory()->active()->create();

    enableHubModule($event, 'hub');

    EventHubSetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
        'buttons_json' => [
            [
                'id' => 'custom-site',
                'type' => 'custom',
                'preset_key' => null,
                'label' => 'Site oficial',
                'icon' => 'link',
                'href' => 'https://example.com',
                'is_visible' => true,
                'opens_in_new_tab' => true,
            ],
            [
                'id' => 'hidden-link',
                'type' => 'custom',
                'preset_key' => null,
                'label' => 'Oculto',
                'icon' => 'link',
                'href' => 'https://hidden.example.com',
                'is_visible' => false,
                'opens_in_new_tab' => true,
            ],
        ],
    ]);

    $this->postJson("/api/v1/public/events/{$event->slug}/hub/click", [
        'button_id' => 'custom-site',
    ])->assertNoContent();

    $this->postJson("/api/v1/public/events/{$event->slug}/hub/click", [
        'button_id' => 'hidden-link',
    ])->assertNoContent();

    $clickEvent = AnalyticsEvent::query()
        ->where('event_id', $event->id)
        ->where('event_name', 'hub.button_click')
        ->first();

    expect($clickEvent)->not->toBeNull()
        ->and(data_get($clickEvent?->metadata_json, 'button_id'))->toBe('custom-site')
        ->and(data_get($clickEvent?->metadata_json, 'button_label'))->toBe('Site oficial')
        ->and(data_get($clickEvent?->metadata_json, 'button_position'))->toBe(1)
        ->and(AnalyticsEvent::query()->where('event_id', $event->id)->where('event_name', 'hub.button_click')->count())->toBe(1);
});

it('tracks public social strip clicks as hub social interactions only when the block is visible', function () {
    $event = Event::factory()->active()->create();

    enableHubModule($event, 'hub');

    EventHubSetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
        'builder_config_json' => [
            'version' => 1,
            'layout_key' => 'classic-cover',
            'theme_key' => 'sunset',
            'theme_tokens' => [
                'page_background' => '#1f2937',
                'page_accent' => '#f97316',
                'surface_background' => '#111827',
                'surface_border' => '#fb923c',
                'text_primary' => '#ffffff',
                'text_secondary' => '#fed7aa',
                'hero_overlay_color' => '#111827',
            ],
            'block_order' => ['hero', 'meta_cards', 'welcome', 'social_strip', 'cta_list'],
            'blocks' => [
                'hero' => [
                    'enabled' => true,
                    'show_logo' => true,
                    'show_badge' => true,
                    'show_meta_cards' => true,
                    'height' => 'md',
                    'overlay_opacity' => 55,
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
                        [
                            'id' => 'social-hidden',
                            'provider' => 'website',
                            'label' => 'Oculto',
                            'href' => 'https://example.com/hidden',
                            'icon' => 'link',
                            'is_visible' => false,
                            'opens_in_new_tab' => true,
                        ],
                    ],
                ],
                'cta_list' => [
                    'enabled' => false,
                    'style' => 'solid',
                    'size' => 'md',
                    'icon_position' => 'left',
                ],
            ],
        ],
    ]);

    $this->postJson("/api/v1/public/events/{$event->slug}/hub/click", [
        'button_id' => 'social-instagram',
    ])->assertNoContent();

    $this->postJson("/api/v1/public/events/{$event->slug}/hub/click", [
        'button_id' => 'social-hidden',
    ])->assertNoContent();

    $socialClick = AnalyticsEvent::query()
        ->where('event_id', $event->id)
        ->where('event_name', 'hub.social_click')
        ->first();

    expect($socialClick)->not->toBeNull()
        ->and(data_get($socialClick?->metadata_json, 'button_id'))->toBe('social-instagram')
        ->and(data_get($socialClick?->metadata_json, 'button_type'))->toBe('social')
        ->and(data_get($socialClick?->metadata_json, 'button_position'))->toBe(1)
        ->and(AnalyticsEvent::query()->where('event_id', $event->id)->where('event_name', 'hub.social_click')->count())->toBe(1)
        ->and(AnalyticsEvent::query()->where('event_id', $event->id)->where('event_name', 'hub.button_click')->count())->toBe(0);
});

it('tracks public sponsor strip clicks as hub sponsor interactions only when the item is visible and linked', function () {
    $event = Event::factory()->active()->create();

    enableHubModule($event, 'hub');

    EventHubSetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
        'builder_config_json' => [
            'version' => 1,
            'layout_key' => 'classic-cover',
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
            'block_order' => ['hero', 'cta_list', 'sponsor_strip'],
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
                    'enabled' => false,
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
                    'title' => 'Informacoes importantes',
                    'style' => 'cards',
                    'columns' => 2,
                    'items' => [],
                ],
                'cta_list' => [
                    'enabled' => false,
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
                    'enabled' => true,
                    'title' => 'Patrocinadores',
                    'style' => 'logos',
                    'items' => [
                        [
                            'id' => 'sponsor-master',
                            'name' => 'Marca One',
                            'subtitle' => 'Master',
                            'logo_path' => 'hub/sponsors/marca-one.png',
                            'href' => 'https://example.com/marca-one',
                            'is_visible' => true,
                            'opens_in_new_tab' => true,
                        ],
                        [
                            'id' => 'sponsor-hidden',
                            'name' => 'Oculto',
                            'subtitle' => null,
                            'logo_path' => null,
                            'href' => 'https://example.com/hidden',
                            'is_visible' => false,
                            'opens_in_new_tab' => true,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $this->postJson("/api/v1/public/events/{$event->slug}/hub/click", [
        'button_id' => 'sponsor-master',
    ])->assertNoContent();

    $this->postJson("/api/v1/public/events/{$event->slug}/hub/click", [
        'button_id' => 'sponsor-hidden',
    ])->assertNoContent();

    $sponsorClick = AnalyticsEvent::query()
        ->where('event_id', $event->id)
        ->where('event_name', 'hub.sponsor_click')
        ->first();

    expect($sponsorClick)->not->toBeNull()
        ->and(data_get($sponsorClick?->metadata_json, 'button_id'))->toBe('sponsor-master')
        ->and(data_get($sponsorClick?->metadata_json, 'button_type'))->toBe('sponsor')
        ->and(data_get($sponsorClick?->metadata_json, 'button_position'))->toBe(1)
        ->and(AnalyticsEvent::query()->where('event_id', $event->id)->where('event_name', 'hub.sponsor_click')->count())->toBe(1);
});

it('returns operational hub insights for the editor', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento com Hub medido',
    ]);

    enableHubModule($event, 'hub');
    enableHubModule($event, 'live');

    EventHubSetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
        'buttons_json' => [
            [
                'id' => 'preset-upload',
                'type' => 'preset',
                'preset_key' => 'upload',
                'label' => 'Enviar fotos',
                'icon' => 'camera',
                'is_visible' => true,
                'opens_in_new_tab' => false,
            ],
            [
                'id' => 'custom-site',
                'type' => 'custom',
                'preset_key' => null,
                'label' => 'Mapa',
                'icon' => 'map-pin',
                'href' => 'https://maps.example.com',
                'is_visible' => true,
                'opens_in_new_tab' => true,
            ],
        ],
    ]);

    $now = CarbonImmutable::now();

    AnalyticsEvent::query()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'event_name' => 'hub.page_view',
        'actor_type' => 'guest',
        'actor_id' => '127.0.0.1',
        'channel' => 'hub',
        'metadata_json' => ['surface' => 'hub'],
        'occurred_at' => $now->subDays(2),
    ]);

    AnalyticsEvent::query()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'event_name' => 'hub.page_view',
        'actor_type' => 'guest',
        'actor_id' => '127.0.0.2',
        'channel' => 'hub',
        'metadata_json' => ['surface' => 'hub'],
        'occurred_at' => $now->subDay(),
    ]);

    AnalyticsEvent::query()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'event_name' => 'hub.page_view',
        'actor_type' => 'user',
        'actor_id' => (string) $user->id,
        'channel' => 'hub',
        'metadata_json' => ['surface' => 'hub'],
        'occurred_at' => $now,
    ]);

    AnalyticsEvent::query()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'event_name' => 'hub.button_click',
        'actor_type' => 'guest',
        'actor_id' => '127.0.0.1',
        'channel' => 'hub',
        'metadata_json' => [
            'surface' => 'hub',
            'button_id' => 'custom-site',
            'button_label' => 'Mapa',
            'button_type' => 'custom',
            'preset_key' => null,
            'button_icon' => 'map-pin',
            'button_position' => 2,
            'resolved_url' => 'https://maps.example.com',
        ],
        'occurred_at' => $now->subDay(),
    ]);

    AnalyticsEvent::query()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'event_name' => 'hub.button_click',
        'actor_type' => 'guest',
        'actor_id' => '127.0.0.2',
        'channel' => 'hub',
        'metadata_json' => [
            'surface' => 'hub',
            'button_id' => 'custom-site',
            'button_label' => 'Mapa',
            'button_type' => 'custom',
            'preset_key' => null,
            'button_icon' => 'map-pin',
            'button_position' => 2,
            'resolved_url' => 'https://maps.example.com',
        ],
        'occurred_at' => $now,
    ]);

    AnalyticsEvent::query()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'event_name' => 'hub.button_click',
        'actor_type' => 'user',
        'actor_id' => (string) $user->id,
        'channel' => 'hub',
        'metadata_json' => [
            'surface' => 'hub',
            'button_id' => 'preset-upload',
            'button_label' => 'Enviar fotos',
            'button_type' => 'preset',
            'preset_key' => 'upload',
            'button_icon' => 'camera',
            'button_position' => 1,
            'resolved_url' => $event->publicUploadUrl(),
        ],
        'occurred_at' => $now,
    ]);

    EventHubSetting::query()->where('event_id', $event->id)->update([
        'builder_config_json' => [
            'version' => 1,
            'layout_key' => 'classic-cover',
            'theme_key' => 'midnight',
            'theme_tokens' => [
                'page_background' => '#020617',
                'page_accent' => '#22c55e',
                'surface_background' => '#0f172a',
                'surface_border' => '#1e293b',
                'text_primary' => '#f8fafc',
                'text_secondary' => '#cbd5e1',
                'hero_overlay_color' => '#020617',
            ],
            'block_order' => ['hero', 'meta_cards', 'welcome', 'social_strip', 'cta_list'],
            'blocks' => [
                'hero' => [
                    'enabled' => true,
                    'show_logo' => true,
                    'show_badge' => true,
                    'show_meta_cards' => true,
                    'height' => 'md',
                    'overlay_opacity' => 55,
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
                    'style' => 'icons',
                    'size' => 'sm',
                    'items' => [
                        [
                            'id' => 'social-instagram',
                            'provider' => 'instagram',
                            'label' => 'Instagram',
                            'href' => 'https://instagram.com/evento',
                            'icon' => 'instagram',
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

    AnalyticsEvent::query()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'event_name' => 'hub.social_click',
        'actor_type' => 'guest',
        'actor_id' => '127.0.0.3',
        'channel' => 'hub',
        'metadata_json' => [
            'surface' => 'hub',
            'button_id' => 'social-instagram',
            'button_label' => 'Instagram',
            'button_type' => 'social',
            'preset_key' => null,
            'button_icon' => 'instagram',
            'button_position' => 1,
            'resolved_url' => 'https://instagram.com/evento',
        ],
        'occurred_at' => $now,
    ]);

    $response = $this->apiGet("/events/{$event->id}/hub/insights?days=30");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.summary.page_views', 3)
        ->assertJsonPath('data.summary.unique_visitors', 3)
        ->assertJsonPath('data.summary.button_clicks', 4)
        ->assertJsonPath('data.summary.active_buttons', 3)
        ->assertJsonPath('data.top_buttons.0.button_id', 'custom-site')
        ->assertJsonPath('data.top_buttons.0.clicks', 2)
        ->assertJsonPath('data.buttons.0.button_id', 'custom-site')
        ->assertJsonPath('data.buttons.0.clicks', 2)
        ->assertJsonPath('data.buttons.2.button_id', 'social-instagram')
        ->assertJsonPath('data.buttons.2.type', 'social')
        ->assertJsonPath('data.buttons.2.clicks', 1)
        ->assertJsonPath('data.window_days', 30);
});
