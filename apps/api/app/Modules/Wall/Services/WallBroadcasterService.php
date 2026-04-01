<?php

namespace App\Modules\Wall\Services;

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Wall\Enums\WallStatus;
use App\Modules\Wall\Events\WallExpired;
use App\Modules\Wall\Events\WallMediaDeleted;
use App\Modules\Wall\Events\WallMediaPublished;
use App\Modules\Wall\Events\WallMediaUpdated;
use App\Modules\Wall\Events\WallSettingsUpdated;
use App\Modules\Wall\Events\WallStatusChanged;
use App\Modules\Wall\Models\EventWallSetting;
use Illuminate\Support\Facades\Storage;

/**
 * Centralized service for all wall broadcasting.
 *
 * Every broadcast goes through here to ensure consistent
 * payload formatting and eligibility checks.
 */
class WallBroadcasterService
{
    // ─── Media Broadcasts ─────────────────────────────────

    /**
     * Broadcast that a new media item should appear on the wall.
     */
    public function broadcastNewMedia(EventMedia $media): void
    {
        $settings = $this->resolveSettings($media->event_id);

        if (! $settings || ! $this->mediaEligibleForWall($media, $settings)) {
            return;
        }

        event(new WallMediaPublished(
            $settings->wall_code,
            $this->mediaPayload($media),
        ));
    }

    /**
     * Broadcast that a media item was updated (e.g., new variant URL).
     */
    public function broadcastMediaUpdated(EventMedia $media): void
    {
        $settings = $this->resolveSettings($media->event_id);

        if (! $settings || ! $this->mediaEligibleForWall($media, $settings)) {
            return;
        }

        event(new WallMediaUpdated(
            $settings->wall_code,
            $this->mediaPayload($media),
        ));
    }

    /**
     * Broadcast that a media item was removed.
     */
    public function broadcastMediaDeleted(EventMedia $media): void
    {
        $settings = $this->resolveSettings($media->event_id);

        if (! $settings || ! $settings->isAvailable()) {
            return;
        }

        event(new WallMediaDeleted(
            $settings->wall_code,
            ['id' => $this->mediaIdentifier($media)],
        ));
    }

    // ─── Settings & Status Broadcasts ─────────────────────

    /**
     * Broadcast that wall settings were updated.
     */
    public function broadcastSettingsUpdated(EventWallSetting $settings): void
    {
        if (! $settings->wall_code || ! $settings->is_enabled) {
            return;
        }

        event(new WallSettingsUpdated(
            $settings->wall_code,
            $this->settingsPayload($settings),
        ));
    }

    /**
     * Broadcast that wall status changed (live, paused, stopped).
     */
    public function broadcastStatusChanged(EventWallSetting $settings, ?string $reason = null): void
    {
        if (! $settings->wall_code) {
            return;
        }

        event(new WallStatusChanged(
            $settings->wall_code,
            $this->statusPayload($settings, $reason),
        ));
    }

    /**
     * Broadcast that wall has expired.
     */
    public function broadcastExpired(EventWallSetting $settings, string $reason = 'expired'): void
    {
        if (! $settings->wall_code) {
            return;
        }

        event(new WallExpired(
            $settings->wall_code,
            [
                'reason'     => $reason,
                'expired_at' => now()->toIso8601String(),
            ],
        ));
    }

    // ─── Payload Builders ─────────────────────────────────

    /**
     * Build the media payload for broadcast.
     */
    public function mediaPayload(EventMedia $media): array
    {
        return [
            'id'          => $this->mediaIdentifier($media),
            'url'         => $this->mediaUrl($media),
            'type'        => $media->media_type ?? 'image',
            'sender_name' => $this->resolveSenderName($media),
            'caption'     => trim((string) ($media->caption ?? '')),
            'is_featured' => (bool) $media->is_featured,
            'created_at'  => ($media->published_at ?? $media->created_at)?->toIso8601String(),
        ];
    }

    /**
     * Build the settings payload for broadcast.
     */
    public function settingsPayload(EventWallSetting $settings): array
    {
        return [
            'interval_ms'        => (int) $settings->interval_ms,
            'queue_limit'        => (int) $settings->queue_limit,
            'layout'             => $settings->layout->value,
            'transition_effect'  => $settings->transition_effect->value,
            'background_url'     => $this->assetUrl($settings->background_image_path),
            'partner_logo_url'   => $this->assetUrl($settings->partner_logo_path),
            'show_qr'            => (bool) $settings->show_qr,
            'show_branding'      => (bool) $settings->show_branding,
            'show_neon'          => (bool) $settings->show_neon,
            'neon_text'          => $settings->neon_text,
            'neon_color'         => $settings->neon_color ?? '#ffffff',
            'show_sender_credit' => (bool) $settings->show_sender_credit,
            'instructions_text'  => $settings->instructions_text,
        ];
    }

    /**
     * Build the status payload for broadcast.
     */
    public function statusPayload(EventWallSetting $settings, ?string $reason = null): array
    {
        return [
            'status'     => $settings->publicStatus(),
            'reason'     => $reason,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    // ─── Helpers ──────────────────────────────────────────

    /**
     * Check if a media item is eligible to appear on the wall.
     */
    private function mediaEligibleForWall(EventMedia $media, EventWallSetting $settings): bool
    {
        return $settings->isPlayable()
            && $media->publication_status?->value === 'published'
            && $media->moderation_status?->value === 'approved'
            && in_array($media->media_type, ['image', 'video'], true);
    }

    /**
     * Resolve wall settings for an event.
     */
    private function resolveSettings(int $eventId): ?EventWallSetting
    {
        return EventWallSetting::query()
            ->where('event_id', $eventId)
            ->first();
    }

    /**
     * Generate a unique identifier for a media item on the wall.
     */
    private function mediaIdentifier(EventMedia $media): string
    {
        return 'media_' . $media->id;
    }

    /**
     * Resolve the best URL for a media item.
     * Prefers the 'wall' variant, falls back to 'gallery', then 'thumb'.
     */
    private function mediaUrl(EventMedia $media): ?string
    {
        if (! $media->relationLoaded('variants')) {
            $media->load('variants');
        }

        $preferredKeys = ['wall', 'gallery', 'thumb'];

        foreach ($preferredKeys as $key) {
            $variant = $media->variants->firstWhere('variant_key', $key);

            if ($variant && $variant->path) {
                return $this->assetUrl($variant->path);
            }
        }

        // Fallback: original file
        $originalFilename = $media->original_filename;

        if ($originalFilename) {
            $basePath = "events/{$media->event_id}/originals/{$originalFilename}";

            return $this->assetUrl($basePath);
        }

        return null;
    }

    /**
     * Resolve sender name from the inbound message chain.
     */
    private function resolveSenderName(EventMedia $media): ?string
    {
        if (! $media->relationLoaded('inboundMessage')) {
            $media->load('inboundMessage');
        }

        return $media->inboundMessage?->sender_name;
    }

    /**
     * Convert a storage path to a public URL.
     */
    private function assetUrl(?string $path): ?string
    {
        if (! $path || trim($path) === '') {
            return null;
        }

        $url = Storage::disk('public')->url($path);

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim((string) config('app.url'), '/') . '/' . ltrim($url, '/');
    }
}
