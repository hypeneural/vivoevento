<?php

namespace App\Modules\Wall\Services;

use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Wall\Models\EventWallSetting;

class WallEligibilityService
{
    public function mediaCanAppear(EventMedia $media, EventWallSetting $settings): bool
    {
        return $settings->isPlayable()
            && $media->publication_status === PublicationStatus::Published
            && $media->moderation_status === ModerationStatus::Approved
            && in_array($media->media_type, ['image', 'video'], true);
    }
}
