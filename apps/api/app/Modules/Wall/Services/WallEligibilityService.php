<?php

namespace App\Modules\Wall\Services;

use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Wall\Enums\WallAcceptedOrientation;
use App\Modules\Wall\Models\EventWallSetting;
use Illuminate\Support\Collection;

class WallEligibilityService
{
    public function __construct(
        private readonly WallVideoAdmissionService $videoAdmission,
    ) {}

    /**
     * Check if a media item can appear on the wall.
     * This is the single gate for wall eligibility.
     */
    public function mediaCanAppear(EventMedia $media, EventWallSetting $settings): bool
    {
        return $settings->isPlayable()
            && $media->publication_status === PublicationStatus::Published
            && $media->moderation_status === ModerationStatus::Approved
            && in_array($media->media_type, ['image', 'video'], true)
            && $this->matchesOrientationRule($media, $settings)
            && $this->passesMediaTypePolicy($media, $settings);
    }

    /**
     * Filter a collection using the same eligibility gate used by realtime broadcasts.
     *
     * @param  Collection<int, EventMedia>  $media
     * @return Collection<int, EventMedia>
     */
    public function filterEligibleMedia(
        Collection $media,
        EventWallSetting $settings,
        ?int $limit = null,
    ): Collection {
        if (! $settings->isPlayable()) {
            return collect();
        }

        $filtered = $media->filter(
            fn (EventMedia $item): bool => $this->mediaCanAppear($item, $settings)
        );

        if ($limit !== null) {
            $filtered = $filtered->take(max(1, $limit));
        }

        return $filtered->values();
    }

    /**
     * Check if media orientation matches the wall's accepted_orientation rule.
     *
     * Rules:
     * - 'all': any media passes
     * - 'landscape': only horizontal + squareish pass
     * - 'portrait': only vertical + squareish pass
     * - null orientation: always passes (unknown = allow)
     */
    public function matchesOrientationRule(EventMedia $media, EventWallSetting $settings): bool
    {
        $acceptedOrientation = $settings->accepted_orientation ?? WallAcceptedOrientation::All;

        return $acceptedOrientation->matches($this->resolveMediaOrientation($media));
    }

    private function passesMediaTypePolicy(EventMedia $media, EventWallSetting $settings): bool
    {
        if ($media->media_type !== 'video') {
            return true;
        }

        $admission = $this->videoAdmission->inspect($media, $settings);
        $preferredVariant = $settings->resolvedVideoPreferredVariant();
        $allowsOriginalFallback = $preferredVariant === 'original';

        if ($admission['state'] === 'blocked') {
            return false;
        }

        if (! $admission['has_minimum_metadata']) {
            return false;
        }

        if (! $admission['preferred_variant_available']) {
            return false;
        }

        if (! $admission['poster_available'] && ! $allowsOriginalFallback) {
            return false;
        }

        if (! $allowsOriginalFallback && $admission['asset_source'] !== 'wall_variant') {
            return false;
        }

        return true;
    }

    /**
     * Resolve the orientation of a media item.
     */
    private function resolveMediaOrientation(EventMedia $media): ?string
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
