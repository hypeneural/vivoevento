<?php

namespace App\Modules\MediaProcessing\Actions;

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaAuditLogger;
use App\Modules\Users\Models\User;

class UpdateEventMediaFeaturedAction
{
    public function __construct(
        private readonly MediaAuditLogger $auditLogger,
    ) {}

    public function execute(EventMedia $eventMedia, bool $isFeatured): EventMedia
    {
        $previousValue = (bool) $eventMedia->is_featured;

        $eventMedia->update([
            'is_featured' => $isFeatured,
        ]);

        $eventMedia = $eventMedia->fresh(['event', 'variants', 'inboundMessage']);
        $actor = auth()->user();

        if ($actor instanceof User) {
            $this->auditLogger->log(
                actor: $actor,
                eventMedia: $eventMedia,
                event: 'media.featured_updated',
                description: $isFeatured ? 'Midia destacada' : 'Destaque removido',
                old: [
                    'is_featured' => $previousValue,
                ],
                attributes: [
                    'is_featured' => (bool) $eventMedia->is_featured,
                ],
            );
        }

        return $eventMedia;
    }
}
