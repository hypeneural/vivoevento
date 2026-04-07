<?php

namespace App\Modules\Wall\Services;

use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Wall\Enums\WallAcceptedOrientation;
use App\Modules\Wall\Models\EventWallSetting;

class WallEligibilityService
{
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
            && $this->matchesOrientationRule($media, $settings);
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
