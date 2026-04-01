<?php

namespace Database\Factories;

use App\Modules\Wall\Enums\WallLayout;
use App\Modules\Wall\Enums\WallStatus;
use App\Modules\Wall\Enums\WallTransition;
use App\Modules\Wall\Models\EventWallSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventWallSettingFactory extends Factory
{
    protected $model = EventWallSetting::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'wall_code' => EventWallSetting::generateUniqueCode(),
            'is_enabled' => false,
            'status' => WallStatus::Draft->value,
            'layout' => WallLayout::Auto->value,
            'transition_effect' => WallTransition::Fade->value,
            'interval_ms' => 8000,
            'queue_limit' => 100,
            'show_qr' => true,
            'show_branding' => true,
            'show_neon' => false,
            'neon_text' => null,
            'neon_color' => '#ffffff',
            'show_sender_credit' => false,
            'background_image_path' => null,
            'partner_logo_path' => null,
            'instructions_text' => null,
            'expires_at' => null,
        ];
    }

    public function live(): static
    {
        return $this->state(fn (): array => [
            'is_enabled' => true,
            'status' => WallStatus::Live->value,
        ]);
    }
}
