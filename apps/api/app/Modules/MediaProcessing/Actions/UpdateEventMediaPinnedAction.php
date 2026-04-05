<?php

namespace App\Modules\MediaProcessing\Actions;

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaAuditLogger;
use App\Modules\Users\Models\User;

class UpdateEventMediaPinnedAction
{
    public function __construct(
        private readonly MediaAuditLogger $auditLogger,
    ) {}

    public function execute(EventMedia $eventMedia, bool $isPinned): EventMedia
    {
        $previousSortOrder = (int) ($eventMedia->sort_order ?? 0);

        if ($isPinned) {
            $nextSortOrder = ((int) EventMedia::query()
                ->where('event_id', $eventMedia->event_id)
                ->max('sort_order')) + 1;

            $eventMedia->update([
                'sort_order' => $nextSortOrder,
            ]);
        } else {
            $eventMedia->update([
                'sort_order' => 0,
            ]);
        }

        $eventMedia = $eventMedia->fresh(['event', 'variants', 'inboundMessage']);
        $actor = auth()->user();

        if ($actor instanceof User) {
            $this->auditLogger->log(
                actor: $actor,
                eventMedia: $eventMedia,
                event: 'media.pinned_updated',
                description: $isPinned ? 'Midia fixada' : 'Midia desafixada',
                old: [
                    'is_pinned' => $previousSortOrder > 0,
                    'sort_order' => $previousSortOrder,
                ],
                attributes: [
                    'is_pinned' => (int) ($eventMedia->sort_order ?? 0) > 0,
                    'sort_order' => (int) ($eventMedia->sort_order ?? 0),
                ],
            );
        }

        return $eventMedia;
    }
}
