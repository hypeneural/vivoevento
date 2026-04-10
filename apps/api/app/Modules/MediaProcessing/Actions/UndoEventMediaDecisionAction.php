<?php

namespace App\Modules\MediaProcessing\Actions;

use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\MediaProcessing\Enums\MediaDecisionSource;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Events\MediaHidden;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaAuditLogger;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UndoEventMediaDecisionAction
{
    public function __construct(
        private readonly MediaAuditLogger $auditLogger,
    ) {}

    public function execute(EventMedia $eventMedia, ?User $actor = null): EventMedia
    {
        return DB::transaction(function () use ($eventMedia, $actor): EventMedia {
            $actor ??= auth()->user() instanceof User ? auth()->user() : null;

            /** @var EventMedia|null $lockedMedia */
            $lockedMedia = EventMedia::query()
                ->whereKey($eventMedia->getKey())
                ->lockForUpdate()
                ->with(['event', 'variants', 'inboundMessage'])
                ->first();

            if (! $lockedMedia) {
                return $eventMedia->fresh(['event', 'variants', 'inboundMessage']) ?? $eventMedia;
            }

            $wasPublished = $lockedMedia->publication_status === PublicationStatus::Published;
            $previousModerationStatus = $lockedMedia->moderation_status?->value;
            $previousPublicationStatus = $lockedMedia->publication_status?->value;
            $previousDecisionSource = $lockedMedia->decision_source?->value;
            $previousDecisionReason = $lockedMedia->decision_override_reason;

            if (
                $previousDecisionSource !== MediaDecisionSource::UserOverride->value
                || ! in_array($previousModerationStatus, [ModerationStatus::Approved->value, ModerationStatus::Rejected->value], true)
            ) {
                throw ValidationException::withMessages([
                    'media' => 'A midia nao possui decisao manual reversivel.',
                ]);
            }

            $lockedMedia->update([
                'moderation_status' => ModerationStatus::Pending,
                'publication_status' => PublicationStatus::Draft,
                'published_at' => null,
                'decision_source' => null,
                'decision_overridden_at' => null,
                'decision_overridden_by_user_id' => null,
                'decision_override_reason' => null,
            ]);

            EventMediaFace::query()
                ->where('event_media_id', $lockedMedia->id)
                ->update([
                    'searchable' => false,
                ]);

            $lockedMedia = $lockedMedia->fresh(['event', 'variants', 'inboundMessage']) ?? $lockedMedia;

            if ($actor instanceof User) {
                $this->auditLogger->log(
                    actor: $actor,
                    eventMedia: $lockedMedia,
                    event: 'media.decision_undone',
                    description: 'Decisao manual desfeita',
                    old: [
                        'moderation_status' => $previousModerationStatus,
                        'publication_status' => $previousPublicationStatus,
                        'decision_source' => $previousDecisionSource,
                        'decision_override_reason' => $previousDecisionReason,
                    ],
                    attributes: [
                        'moderation_status' => $lockedMedia->moderation_status?->value,
                        'publication_status' => $lockedMedia->publication_status?->value,
                        'decision_source' => $lockedMedia->decision_source?->value,
                        'decision_override_reason' => $lockedMedia->decision_override_reason,
                    ],
                );
            }

            if ($wasPublished && $previousDecisionSource === MediaDecisionSource::UserOverride->value) {
                event(MediaHidden::fromMedia($lockedMedia));
            }

            return $lockedMedia;
        }, 3);
    }
}
