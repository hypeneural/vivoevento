<?php

namespace App\Modules\Wall\Services;

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use App\Modules\Wall\Models\EventWallSetting;

class WallVideoAdmissionService
{
    /**
     * @return array{
     *   state:'eligible'|'eligible_with_fallback'|'blocked',
     *   reasons:array<int, string>,
     *   has_minimum_metadata:bool,
     *   supported_format:bool,
     *   preferred_variant_available:bool,
     *   preferred_variant_key:string|null,
     *   poster_available:bool,
     *   poster_variant_key:string|null,
     *   asset_source:'wall_variant'|'original',
     *   duration_limit_seconds:int
     * }
     */
    public function inspect(EventMedia $media, ?EventWallSetting $settings = null): array
    {
        if (! $media->relationLoaded('variants')) {
            $media->load('variants');
        }

        $reasons = [];
        $hasMinimumMetadata = $this->hasMinimumMetadata($media);
        $supportedFormat = $this->hasSupportedFormat($media);
        $videoEnabled = $settings?->resolvedVideoEnabled() ?? (bool) config('media_processing.wall_video.enabled', true);
        $allowsOriginalPlayback = $this->allowsOriginalPlayback($settings);
        $preferredVariant = $this->resolvePreferredVariant($media, $settings);
        $posterVariant = $this->resolvePosterVariant($media);

        if (! $videoEnabled) {
            $reasons[] = 'video_disabled';
        }

        if (! $hasMinimumMetadata) {
            $reasons[] = 'missing_metadata';
        }

        if ($this->isDurationOverLimit($media, $settings)) {
            $reasons[] = 'duration_over_limit';
        }

        if (! $supportedFormat) {
            $reasons[] = 'unsupported_format';
        }

        if (! $preferredVariant && ! $allowsOriginalPlayback) {
            $reasons[] = 'variant_missing';
        }

        if (! $posterVariant) {
            $reasons[] = 'poster_missing';
        }

        $blockingReasons = ['video_disabled', 'duration_over_limit', 'unsupported_format'];
        $state = collect($reasons)->contains(fn (string $reason): bool => in_array($reason, $blockingReasons, true))
            ? 'blocked'
            : ($reasons === [] ? 'eligible' : 'eligible_with_fallback');

        return [
            'state' => $state,
            'reasons' => array_values(array_unique($reasons)),
            'has_minimum_metadata' => $hasMinimumMetadata,
            'supported_format' => $supportedFormat,
            'preferred_variant_available' => $preferredVariant !== null || $allowsOriginalPlayback,
            'preferred_variant_key' => $preferredVariant?->variant_key ?? ($allowsOriginalPlayback ? 'original' : null),
            'poster_available' => $posterVariant !== null,
            'poster_variant_key' => $posterVariant?->variant_key,
            'asset_source' => $preferredVariant ? 'wall_variant' : 'original',
            'duration_limit_seconds' => $this->durationLimitSeconds($settings),
        ];
    }

    private function hasMinimumMetadata(EventMedia $media): bool
    {
        return (int) ($media->width ?? 0) > 0
            && (int) ($media->height ?? 0) > 0
            && (int) ($media->duration_seconds ?? 0) > 0;
    }

    private function isDurationOverLimit(EventMedia $media, ?EventWallSetting $settings = null): bool
    {
        return (int) ($media->duration_seconds ?? 0) > $this->durationLimitSeconds($settings);
    }

    private function hasSupportedFormat(EventMedia $media): bool
    {
        $container = strtolower(trim((string) ($media->container ?? '')));
        $videoCodec = strtolower(trim((string) ($media->video_codec ?? '')));
        $mimeType = strtolower(trim((string) ($media->mime_type ?? '')));

        if ($container !== '' && ! in_array($container, ['mp4', 'mov', 'm4v'], true)) {
            return false;
        }

        if ($videoCodec !== '' && ! in_array($videoCodec, ['h264', 'avc1'], true)) {
            return false;
        }

        if ($container === '' && $videoCodec === '' && $mimeType !== '') {
            return str_starts_with($mimeType, 'video/mp4') || str_starts_with($mimeType, 'video/quicktime');
        }

        return true;
    }

    private function resolvePreferredVariant(EventMedia $media, ?EventWallSetting $settings = null): ?EventMediaVariant
    {
        $preferredVariant = $settings?->resolvedVideoPreferredVariant();

        if ($preferredVariant === 'original') {
            return null;
        }

        $variantOrder = array_values(array_unique(array_filter([
            $preferredVariant,
            'wall_video_720p',
            'wall_video_1080p',
            'wall',
        ])));

        foreach ($variantOrder as $variantKey) {
            $variant = $media->variants->firstWhere('variant_key', $variantKey);

            if ($variant?->path) {
                return $variant;
            }
        }

        return null;
    }

    private function resolvePosterVariant(EventMedia $media): ?EventMediaVariant
    {
        foreach (['wall_video_poster', 'poster', 'thumb', 'fast_preview'] as $variantKey) {
            $variant = $media->variants->firstWhere('variant_key', $variantKey);

            if ($variant?->path) {
                return $variant;
            }
        }

        return null;
    }

    private function durationLimitSeconds(?EventWallSetting $settings = null): int
    {
        if ($settings) {
            return $settings->resolvedVideoMaxSeconds();
        }

        return max(1, (int) config('media_processing.wall_video.max_duration_seconds', 30));
    }

    private function allowsOriginalPlayback(?EventWallSetting $settings = null): bool
    {
        return ($settings?->resolvedVideoPreferredVariant() ?? 'wall_video_720p') === 'original';
    }
}
