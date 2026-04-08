<?php

namespace App\Modules\Wall\Services;

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\Wall\Support\WallSelectionPreset;
use App\Shared\Support\AssetUrlService;
use App\Shared\Support\PhoneNumber;
use Illuminate\Support\Str;

class WallPayloadFactory
{
    public function __construct(
        private readonly MediaAssetUrlService $mediaAssets,
        private readonly AssetUrlService $assets,
    ) {}

    public function media(EventMedia $media): array
    {
        return [
            'id' => $this->mediaIdentifier($media),
            'url' => $this->mediaAssets->wall($media),
            'preview_url' => $this->mediaAssets->thumbnail($media),
            'original_url' => $this->mediaAssets->original($media),
            'type' => $media->media_type ?? 'image',
            'sender_name' => $this->resolveSenderName($media),
            'sender_key' => $this->resolveSenderKey($media),
            'source_type' => $media->source_type,
            'caption' => trim((string) ($media->caption ?? '')),
            'duplicate_cluster_key' => $media->duplicate_group_key,
            'is_featured' => (bool) $media->is_featured,
            'width' => $media->width,
            'height' => $media->height,
            'orientation' => $this->resolveOrientation($media),
            'created_at' => ($media->published_at ?? $media->created_at)?->toIso8601String(),
        ];
    }

    public function deletedMedia(EventMedia $media): array
    {
        return ['id' => $this->mediaIdentifier($media)];
    }

    public function settings(EventWallSetting $settings, bool $runtime = false): array
    {
        $rawPolicy = WallSelectionPreset::normalizePolicy(
            $settings->selection_policy,
            $settings->selection_mode,
        );
        $effectivePolicy = $runtime
            ? WallSelectionPreset::applyPhasePolicy($rawPolicy, $settings->event_phase)
            : $rawPolicy;
        $intervalMs = $runtime
            ? WallSelectionPreset::applyPhaseInterval((int) $settings->interval_ms, $settings->event_phase)
            : (int) $settings->interval_ms;

        return [
            'interval_ms' => $intervalMs,
            'queue_limit' => (int) $settings->queue_limit,
            'selection_mode' => $settings->selection_mode?->value ?? 'balanced',
            'event_phase' => $settings->event_phase?->value ?? 'flow',
            'selection_policy' => $effectivePolicy,
            'layout' => $settings->layout->value,
            'transition_effect' => $settings->transition_effect->value,
            'background_url' => $this->assets->toPublicUrl($settings->background_image_path),
            'partner_logo_url' => $this->assets->toPublicUrl($settings->partner_logo_path),
            'show_qr' => (bool) $settings->show_qr,
            'show_branding' => (bool) $settings->show_branding,
            'show_neon' => (bool) $settings->show_neon,
            'neon_text' => $settings->neon_text,
            'neon_color' => $settings->neon_color ?? '#ffffff',
            'show_sender_credit' => (bool) $settings->show_sender_credit,
            'show_side_thumbnails' => (bool) ($settings->show_side_thumbnails ?? true),
            'accepted_orientation' => $settings->accepted_orientation?->value ?? 'all',
            'ad_mode' => $settings->ad_mode ?? 'disabled',
            'ad_frequency' => (int) ($settings->ad_frequency ?? 5),
            'ad_interval_minutes' => (int) ($settings->ad_interval_minutes ?? 3),
            'instructions_text' => $settings->instructions_text,
        ];
    }

    /**
     * Build the ads payload for the boot response.
     */
    public function ads(EventWallSetting $settings): array
    {
        $ads = $settings->activeAds()->get();

        return $ads->map(fn ($ad) => [
            'id' => $ad->id,
            'url' => $this->assets->toPublicUrl($ad->file_path),
            'media_type' => $ad->media_type,
            'duration_seconds' => (int) $ad->duration_seconds,
            'position' => (int) $ad->position,
        ])->all();
    }

    public function status(EventWallSetting $settings, ?string $reason = null): array
    {
        return [
            'status' => $settings->publicStatus(),
            'reason' => $reason,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    private function mediaIdentifier(EventMedia $media): string
    {
        return 'media_'.$media->id;
    }

    private function resolveSenderName(EventMedia $media): ?string
    {
        if (! $media->relationLoaded('inboundMessage')) {
            $media->load('inboundMessage');
        }

        return $media->inboundMessage?->sender_name
            ?: ($media->source_label ? trim((string) $media->source_label) : null);
    }

    private function resolveSenderKey(EventMedia $media): string
    {
        if (! $media->relationLoaded('inboundMessage')) {
            $media->load('inboundMessage');
        }

        $phone = PhoneNumber::normalizeBrazilianWhatsAppOrNull($media->inboundMessage?->sender_phone);

        if ($phone) {
            return 'whatsapp:'.$phone;
        }

        if ($media->uploaded_by_user_id) {
            return 'user:'.$media->uploaded_by_user_id;
        }

        if ($media->source_type && $media->source_label) {
            return 'source:'.Str::slug($media->source_type.'-'.$media->source_label);
        }

        $senderName = $this->resolveSenderName($media);
        if ($senderName) {
            return 'guest:'.Str::slug($senderName);
        }

        return 'media:'.$media->id;
    }

    private function resolveOrientation(EventMedia $media): ?string
    {
        $width = (int) ($media->width ?? 0);
        $height = (int) ($media->height ?? 0);

        if ($width <= 0 || $height <= 0) {
            return null;
        }

        if ($height > $width) {
            return 'vertical';
        }

        if ($width > $height) {
            return 'horizontal';
        }

        return 'squareish';
    }
}
