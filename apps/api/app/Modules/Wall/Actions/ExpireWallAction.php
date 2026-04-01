<?php

namespace App\Modules\Wall\Actions;

use App\Modules\Wall\Enums\WallStatus;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\Wall\Services\WallBroadcasterService;

class ExpireWallAction
{
    public function __construct(
        private readonly WallBroadcasterService $broadcaster,
    ) {}

    /**
     * Expire the wall — terminal state. Broadcast immediately.
     */
    public function execute(int $eventId, string $reason = 'expired_by_admin'): EventWallSetting
    {
        $settings = EventWallSetting::where('event_id', $eventId)->firstOrFail();

        if ($settings->status === WallStatus::Expired) {
            return $settings;
        }

        $settings->update([
            'status'     => WallStatus::Expired,
            'is_enabled' => false,
            'expires_at' => $settings->expires_at ?? now(),
        ]);

        $settings->refresh();

        $this->broadcaster->broadcastExpired($settings, $reason);

        return $settings;
    }
}
