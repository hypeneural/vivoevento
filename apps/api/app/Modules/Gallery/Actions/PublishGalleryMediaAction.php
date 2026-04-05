<?php

namespace App\Modules\Gallery\Actions;

use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Events\MediaPublished;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaAuditLogger;
use App\Modules\MediaProcessing\Services\ModerationBroadcasterService;
use App\Modules\Users\Models\User;
use Illuminate\Validation\ValidationException;

class PublishGalleryMediaAction
{
    public function __construct(
        private readonly MediaAuditLogger $auditLogger,
        private readonly ModerationBroadcasterService $moderationBroadcaster,
    ) {}

    public function execute(EventMedia $eventMedia, ?User $actor = null): EventMedia
    {
        if ($eventMedia->moderation_status !== ModerationStatus::Approved) {
            throw ValidationException::withMessages([
                'media' => ['Somente midias aprovadas podem ser publicadas na galeria.'],
            ]);
        }

        if ($eventMedia->publication_status === PublicationStatus::Published) {
            return $eventMedia->fresh(['event', 'variants', 'inboundMessage']);
        }

        $previousPublicationStatus = $eventMedia->publication_status?->value;
        $previousPublishedAt = $eventMedia->published_at?->toIso8601String();
        $actor ??= auth()->user() instanceof User ? auth()->user() : null;

        $eventMedia->update([
            'publication_status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);

        $eventMedia = $eventMedia->fresh(['event', 'variants', 'inboundMessage']);

        if ($actor instanceof User) {
            $this->auditLogger->log(
                actor: $actor,
                eventMedia: $eventMedia,
                event: 'gallery.published',
                description: 'Midia publicada na galeria',
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

        event(MediaPublished::fromMedia($eventMedia));
        $this->moderationBroadcaster->broadcastUpdated($eventMedia);

        return $eventMedia;
    }
}
