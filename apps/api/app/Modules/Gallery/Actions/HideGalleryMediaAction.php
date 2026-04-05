<?php

namespace App\Modules\Gallery\Actions;

use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Events\MediaHidden;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaAuditLogger;
use App\Modules\MediaProcessing\Services\ModerationBroadcasterService;
use App\Modules\Users\Models\User;

class HideGalleryMediaAction
{
    public function __construct(
        private readonly MediaAuditLogger $auditLogger,
        private readonly ModerationBroadcasterService $moderationBroadcaster,
    ) {}

    public function execute(EventMedia $eventMedia, ?User $actor = null): EventMedia
    {
        if ($eventMedia->publication_status === PublicationStatus::Hidden) {
            return $eventMedia->fresh(['event', 'variants', 'inboundMessage']);
        }

        $previousPublicationStatus = $eventMedia->publication_status?->value;
        $previousPublishedAt = $eventMedia->published_at?->toIso8601String();
        $actor ??= auth()->user() instanceof User ? auth()->user() : null;

        $eventMedia->update([
            'publication_status' => PublicationStatus::Hidden,
            'published_at' => null,
        ]);

        $eventMedia = $eventMedia->fresh(['event', 'variants', 'inboundMessage']);

        if ($actor instanceof User) {
            $this->auditLogger->log(
                actor: $actor,
                eventMedia: $eventMedia,
                event: 'gallery.hidden',
                description: 'Midia ocultada da galeria',
                old: [
                    'publication_status' => $previousPublicationStatus,
                    'published_at' => $previousPublishedAt,
                ],
                attributes: [
                    'publication_status' => $eventMedia->publication_status?->value,
                    'published_at' => $eventMedia->published_at?->toIso8601String(),
                ],
            );
        }

        event(MediaHidden::fromMedia($eventMedia));
        $this->moderationBroadcaster->broadcastUpdated($eventMedia);

        return $eventMedia;
    }
}
