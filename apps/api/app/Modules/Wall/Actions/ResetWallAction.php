<?php

namespace App\Modules\Wall\Actions;

use App\Modules\Wall\Enums\WallLayout;
use App\Modules\Wall\Enums\WallStatus;
use App\Modules\Wall\Enums\WallTransition;
use App\Modules\Wall\Enums\WallTransitionMode;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\Wall\Services\WallBroadcasterService;

class ResetWallAction
{
    public function __construct(
        private readonly WallBroadcasterService $broadcaster,
    ) {}

    /**
     * Reset wall to draft state with default settings.
     * Generates a new wall_code (invalidates any existing player URLs).
     */
    public function execute(int $eventId): EventWallSetting
    {
        $settings = EventWallSetting::where('event_id', $eventId)->firstOrFail();

        $oldWallCode = $settings->wall_code;

        $settings->update([
            'wall_code'            => EventWallSetting::generateUniqueCode(),
            'is_enabled'           => false,
            'status'               => WallStatus::Draft,
            'layout'               => WallLayout::Auto,
            'transition_effect'    => WallTransition::Fade,
            'transition_mode'      => WallTransitionMode::Fixed,
            'interval_ms'          => 8000,
            'queue_limit'          => 100,
            'show_qr'              => true,
            'show_branding'        => true,
            'show_neon'            => false,
            'neon_text'            => null,
            'neon_color'           => '#ffffff',
            'show_sender_credit'   => false,
            'background_image_path' => null,
            'partner_logo_path'    => null,
            'instructions_text'    => null,
            'expires_at'           => null,
        ]);

        $settings->refresh();

        // Broadcast expired on the OLD code so existing players disconnect
        if ($oldWallCode) {
            $this->broadcaster->broadcastExpired(
                // Temporarily set old code for the broadcast
                tap($settings->replicate(), fn ($s) => $s->wall_code = $oldWallCode),
                'reset',
            );
        }

        return $settings;
    }
}
