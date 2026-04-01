<?php

namespace App\Modules\Wall\Actions;

use App\Modules\Wall\Enums\WallStatus;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\Wall\Services\WallBroadcasterService;

class StopWallAction
{
    public function __construct(
        private readonly WallBroadcasterService $broadcaster,
    ) {}

    /**
     * Stop/pause the wall — set status to paused and broadcast immediately.
     */
    public function execute(int $eventId, WallStatus $targetStatus = WallStatus::Paused): EventWallSetting
    {
        $settings = EventWallSetting::where('event_id', $eventId)->firstOrFail();

        if ($settings->status === $targetStatus) {
            return $settings;
        }

        $previousStatus = $settings->status;

        $settings->update([
            'status'     => $targetStatus,
            'is_enabled' => $targetStatus !== WallStatus::Stopped,
        ]);

        $settings->refresh();

        $reason = match ($targetStatus) {
            WallStatus::Paused  => 'paused_by_admin',
            WallStatus::Stopped => 'stopped_by_admin',
            default             => "changed_from_{$previousStatus->value}",
        };

        $this->broadcaster->broadcastStatusChanged($settings, $reason);

        return $settings;
    }
}
