<?php

namespace App\Modules\Wall\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\Wall\Enums\WallStatus;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\Wall\Services\WallBroadcasterService;
use App\Modules\Wall\Services\WallEventGuardService;
use RuntimeException;

class StartWallAction
{
    public function __construct(
        private readonly WallBroadcasterService $broadcaster,
        private readonly WallEventGuardService $guard,
    ) {}

    /**
     * Activate the wall — set status to live and broadcast immediately.
     */
    public function execute(int $eventId): EventWallSetting
    {
        $event = Event::query()->with('modules')->findOrFail($eventId);

        $this->guard->ensureCanStart($event);

        $settings = EventWallSetting::firstOrCreate(
            ['event_id' => $event->id],
        );

        $currentStatus = $settings->status;

        if ($currentStatus === WallStatus::Expired) {
            throw new RuntimeException('Não é possível iniciar um wall expirado. Use o reset primeiro.');
        }

        if ($currentStatus === WallStatus::Live) {
            return $settings;
        }

        $settings->update([
            'status'     => WallStatus::Live,
            'is_enabled' => true,
        ]);

        $settings->refresh();

        $this->broadcaster->broadcastStatusChanged(
            $settings,
            "started_from_{$currentStatus->value}",
        );

        return $settings;
    }
}
