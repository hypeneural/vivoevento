<?php

namespace App\Modules\Wall\Services;

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Wall\Events\WallExpired;
use App\Modules\Wall\Events\WallMediaDeleted;
use App\Modules\Wall\Events\WallMediaPublished;
use App\Modules\Wall\Events\WallMediaUpdated;
use App\Modules\Wall\Events\PrivateWallExpired;
use App\Modules\Wall\Events\WallPlayerCommanded;
use App\Modules\Wall\Events\PrivateWallSettingsUpdated;
use App\Modules\Wall\Events\PrivateWallStatusChanged;
use App\Modules\Wall\Events\WallSettingsUpdated;
use App\Modules\Wall\Events\WallStatusChanged;
use App\Modules\Wall\Models\EventWallSetting;

class WallBroadcasterService
{
    public function __construct(
        private readonly WallEligibilityService $eligibility,
        private readonly WallPayloadFactory $payloads,
    ) {}

    public function broadcastNewMedia(EventMedia $media): void
    {
        $settings = $this->resolveSettings($media->event_id);

        if (! $settings || ! $this->eligibility->mediaCanAppear($media, $settings)) {
            return;
        }

        event(new WallMediaPublished(
            $settings->wall_code,
            $this->payloads->media($media),
        ));
    }

    public function broadcastMediaUpdated(EventMedia $media): void
    {
        $settings = $this->resolveSettings($media->event_id);

        if (! $settings || ! $this->eligibility->mediaCanAppear($media, $settings)) {
            return;
        }

        event(new WallMediaUpdated(
            $settings->wall_code,
            $this->payloads->media($media),
        ));
    }

    public function broadcastMediaDeleted(EventMedia $media): void
    {
        $settings = $this->resolveSettings($media->event_id);

        if (! $settings || ! $settings->isAvailable()) {
            return;
        }

        event(new WallMediaDeleted(
            $settings->wall_code,
            $this->payloads->deletedMedia($media),
        ));
    }

    public function broadcastSettingsUpdated(EventWallSetting $settings): void
    {
        if (! $settings->wall_code || ! $settings->isAvailable()) {
            return;
        }

        event(new WallSettingsUpdated(
            $settings->wall_code,
            $this->payloads->settings($settings, runtime: true),
        ));

        event(new PrivateWallSettingsUpdated(
            $settings->event_id,
            $this->payloads->settings($settings, runtime: false),
        ));
    }

    public function broadcastStatusChanged(EventWallSetting $settings, ?string $reason = null): void
    {
        if (! $settings->wall_code) {
            return;
        }

        event(new WallStatusChanged(
            $settings->wall_code,
            $this->payloads->status($settings, $reason),
        ));

        event(new PrivateWallStatusChanged(
            $settings->event_id,
            $this->payloads->status($settings, $reason),
        ));
    }

    public function broadcastExpired(EventWallSetting $settings, string $reason = 'expired'): void
    {
        if (! $settings->wall_code) {
            return;
        }

        event(new WallExpired(
            $settings->wall_code,
            [
                'reason' => $reason,
                'expired_at' => now()->toIso8601String(),
            ],
        ));

        event(new PrivateWallExpired(
            $settings->event_id,
            [
                'reason' => $reason,
                'expired_at' => now()->toIso8601String(),
            ],
        ));
    }

    public function broadcastPlayerCommand(EventWallSetting $settings, array $payload): void
    {
        if (! $settings->wall_code || ! $settings->isAvailable()) {
            return;
        }

        event(new WallPlayerCommanded(
            $settings->wall_code,
            $payload,
        ));
    }

    public function broadcastAdsUpdated(EventWallSetting $settings): void
    {
        if (! $settings->wall_code || ! $settings->isAvailable()) {
            return;
        }

        event(new \App\Modules\Wall\Events\WallAdsUpdated(
            $settings->wall_code,
            ['ads' => $this->payloads->ads($settings)],
        ));
    }

    private function resolveSettings(int $eventId): ?EventWallSetting
    {
        return EventWallSetting::query()
            ->where('event_id', $eventId)
            ->first();
    }
}
