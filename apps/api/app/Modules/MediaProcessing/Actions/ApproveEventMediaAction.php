<?php

namespace App\Modules\MediaProcessing\Actions;

use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\MediaProcessing\Enums\MediaDecisionSource;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Jobs\PublishMediaJob;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaAuditLogger;
use App\Modules\Users\Models\User;

class ApproveEventMediaAction
{
    public function __construct(
        private readonly MediaAuditLogger $auditLogger,
    ) {}

    public function execute(EventMedia $eventMedia, ?User $actor = null, ?string $reason = null): EventMedia
    {
        $previousModerationStatus = $eventMedia->moderation_status?->value;
        $previousPublicationStatus = $eventMedia->publication_status?->value;
        $actor ??= auth()->user() instanceof User ? auth()->user() : null;
        $trimmedReason = is_string($reason) ? trim($reason) : null;

        $eventMedia->update([
            'moderation_status' => ModerationStatus::Approved,
            'decision_source' => MediaDecisionSource::UserOverride,
            'decision_overridden_at' => now(),
            'decision_overridden_by_user_id' => $actor?->id,
            'decision_override_reason' => $trimmedReason !== '' ? $trimmedReason : null,
        ]);

        EventMediaFace::query()
            ->where('event_media_id', $eventMedia->id)
            ->update([
                'searchable' => true,
            ]);

        $eventMedia = $eventMedia->fresh(['event', 'variants', 'inboundMessage']);

        if ($actor instanceof User) {
            $this->auditLogger->log(
                actor: $actor,
                eventMedia: $eventMedia,
                event: 'media.approved',
                description: 'Midia aprovada',
                old: [
                    'moderation_status' => $previousModerationStatus,
                    'publication_status' => $previousPublicationStatus,
                ],
                attributes: [
                    'moderation_status' => $eventMedia->moderation_status?->value,
                    'publication_status' => $eventMedia->publication_status?->value,
                    'decision_source' => $eventMedia->decision_source?->value,
                    'decision_overridden_at' => $eventMedia->decision_overridden_at?->toIso8601String(),
                    'decision_overridden_by_user_id' => $eventMedia->decision_overridden_by_user_id,
                    'decision_override_reason' => $eventMedia->decision_override_reason,
                ],
            );
        }

        PublishMediaJob::dispatchSync($eventMedia->id);

        return $eventMedia->fresh(['event', 'variants', 'inboundMessage']);
    }
}
