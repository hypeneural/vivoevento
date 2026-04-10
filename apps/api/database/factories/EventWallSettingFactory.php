<?php

namespace Database\Factories;

use App\Modules\Wall\Enums\WallLayout;
use App\Modules\Wall\Enums\WallEventPhase;
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
            'event_phase' => WallEventPhase::Flow->value,
            'show_qr' => true,
            'show_branding' => true,
            'show_neon' => false,
            'neon_text' => null,
            'neon_color' => '#ffffff',
            'show_sender_credit' => false,
            'show_side_thumbnails' => true,
            'accepted_orientation' => 'all',
            'video_enabled' => true,
            'public_upload_video_enabled' => true,
            'private_inbound_video_enabled' => true,
            'video_playback_mode' => 'play_to_end_if_short_else_cap',
            'video_max_seconds' => 30,
            'video_resume_mode' => 'resume_if_same_item_else_restart',
            'video_audio_policy' => 'muted',
            'video_multi_layout_policy' => 'disallow',
            'video_preferred_variant' => 'wall_video_720p',
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
