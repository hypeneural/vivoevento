<?php

namespace App\Modules\Wall\Services;

use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\Wall\Models\EventWallSetting;
use Illuminate\Support\Collection;

class WallRuntimeMediaService
{
    public function loadPlayableMedia(EventWallSetting $settings, ?int $queueLimit = null): Collection
    {
        $limit = $queueLimit ?? max(1, (int) $settings->queue_limit);

        return $settings->event->media()
            ->where('moderation_status', ModerationStatus::Approved)
            ->where('publication_status', PublicationStatus::Published)
            ->whereIn('media_type', ['image', 'video'])
            ->with(['variants', 'inboundMessage'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
