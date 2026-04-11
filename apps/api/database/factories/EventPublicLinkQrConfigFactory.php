<?php

namespace Database\Factories;

use App\Modules\Events\Models\EventPublicLinkQrConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventPublicLinkQrConfigFactory extends Factory
{
    protected $model = EventPublicLinkQrConfig::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'link_key' => 'gallery',
            'config_version' => 'event-public-link-qr.v1',
            'config_json' => [
                'config_version' => 'event-public-link-qr.v1',
                'usage_preset' => 'galeria_premium',
                'skin_preset' => 'classico',
                'render' => [
                    'preview_type' => 'svg',
                    'preview_size' => 320,
                    'margin_modules' => 4,
                    'background_mode' => 'solid',
                ],
                'style' => [
                    'dots' => ['type' => 'rounded', 'color' => '#0f172a', 'gradient' => null],
                    'corners_square' => ['type' => 'extra-rounded', 'color' => '#0f172a', 'gradient' => null],
                    'corners_dot' => ['type' => 'dot', 'color' => '#0f172a', 'gradient' => null],
                    'background' => ['color' => '#ffffff', 'gradient' => null, 'transparent' => false],
                ],
                'logo' => [
                    'mode' => 'none',
                    'asset_path' => null,
                    'asset_url' => null,
                    'image_size' => 0.22,
                    'margin_px' => 8,
                    'hide_background_dots' => true,
                    'save_as_blob' => true,
                ],
                'advanced' => [
                    'error_correction_level' => 'Q',
                    'shape' => 'square',
                    'round_size' => true,
                    'type_number' => 0,
                    'mode' => 'Byte',
                ],
                'export_defaults' => [
                    'extension' => 'svg',
                    'size' => 1024,
                    'download_name_pattern' => 'evento-{event_id}-{link_key}',
                ],
            ],
        ];
    }
}
