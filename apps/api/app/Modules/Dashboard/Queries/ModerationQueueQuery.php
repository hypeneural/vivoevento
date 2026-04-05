<?php

namespace App\Modules\Dashboard\Queries;

use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\Storage;

class ModerationQueueQuery
{
    /**
     * Returns up to 8 pending media thumbnails for the moderation widget.
     */
    public function execute(int $organizationId): array
    {
        $media = EventMedia::query()
            ->whereHas('event', fn ($q) => $q->where('organization_id', $organizationId))
            ->where('moderation_status', 'pending')
            ->with('variants')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['id', 'event_id', 'original_filename']);

        return $media->map(function (EventMedia $m) {
            $thumb = $m->variants->firstWhere('variant_key', 'thumb');
            $thumbPath = $thumb?->path;

            $url = null;
            if ($thumbPath) {
                $url = preg_match('/^https?:\/\//i', $thumbPath)
                    ? $thumbPath
                    : Storage::disk('public')->url($thumbPath);
            }

            return [
                'id'            => $m->id,
                'thumbnail_url' => $url,
            ];
        })->all();
    }
}
