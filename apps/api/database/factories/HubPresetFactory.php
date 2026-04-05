<?php

namespace Database\Factories;

use App\Modules\Events\Models\Event;
use App\Modules\Hub\Models\HubPreset;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HubPreset>
 */
class HubPresetFactory extends Factory
{
    protected $model = HubPreset::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by_user_id' => User::factory(),
            'source_event_id' => Event::factory(),
            'name' => 'Modelo ' . fake()->unique()->word(),
            'description' => fake()->sentence(),
            'theme_key' => 'sunset',
            'layout_key' => 'hero-cards',
            'preset_payload_json' => [
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
                    'block_order' => ['hero', 'meta_cards', 'countdown', 'cta_list'],
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
                            'enabled' => false,
                            'style' => 'chips',
                            'size' => 'md',
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
                'buttons' => [],
            ],
        ];
    }
}
