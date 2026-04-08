<?php

namespace App\Modules\MediaProcessing\Services;

use App\Modules\MediaProcessing\Enums\MediaProcessingStatus;
use App\Modules\MediaProcessing\Enums\MediaDecisionSource;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;

class MediaEffectiveStateResolver
{
    /**
     * @return array<string, bool|string|null>
     */
    public function resolve(EventMedia $media): array
    {
        $media->loadMissing('event.contentModerationSettings', 'event.mediaIntelligenceSettings');

        $safetyDecision = $this->safetyDecision($media);
        $contextDecision = $this->contextDecision($media);
        $operatorDecision = $this->operatorDecision($media);
        $publicationDecision = $this->publicationDecision($media);
        $safetyIsBlocking = $this->safetyIsBlocking($media);
        $contextIsBlocking = $this->contextIsBlocking($media);

        return [
            'effective_media_state' => $this->effectiveMediaState(
                $media,
                $safetyDecision,
                $contextDecision,
                $operatorDecision,
                $publicationDecision,
                $safetyIsBlocking,
                $contextIsBlocking,
            ),
            'safety_decision' => $safetyDecision,
            'safety_is_blocking' => $safetyIsBlocking,
            'context_decision' => $contextDecision,
            'context_is_blocking' => $contextIsBlocking,
            'operator_decision' => $operatorDecision,
            'publication_decision' => $publicationDecision,
        ];
    }

    private function safetyDecision(EventMedia $media): string
    {
        return match ($media->safety_status) {
            null, 'queued' => 'pending',
            'pass' => 'approved',
            'review' => 'review',
            'block' => 'rejected',
            'failed' => 'failed',
            'skipped' => 'skipped',
            default => 'unknown',
        };
    }

    private function contextDecision(EventMedia $media): string
    {
        return match ($media->vlm_status) {
            null, 'queued' => 'pending',
            'completed' => 'approved',
            'review' => 'review',
            'rejected' => 'rejected',
            'failed' => 'failed',
            'skipped' => 'skipped',
            default => 'unknown',
        };
    }

    private function operatorDecision(EventMedia $media): string
    {
        if ($media->decision_source !== MediaDecisionSource::UserOverride) {
            return 'none';
        }

        return match ($media->moderation_status) {
            ModerationStatus::Approved => 'approved',
            ModerationStatus::Rejected => 'rejected',
            default => 'pending',
        };
    }

    private function publicationDecision(EventMedia $media): string
    {
        return $media->publication_status?->value ?? 'none';
    }

    private function safetyIsBlocking(EventMedia $media): bool
    {
        return $media->media_type === 'image'
            && $media->event?->isAiModeration()
            && ! $media->event?->isContentModerationObserveOnly()
            && (bool) ($media->event?->contentModerationSettings?->enabled ?? false);
    }

    private function contextIsBlocking(EventMedia $media): bool
    {
        return $media->media_type === 'image'
            && $media->event?->isAiModeration()
            && (bool) ($media->event?->mediaIntelligenceSettings?->enabled ?? false)
            && ($media->event?->mediaIntelligenceSettings?->mode === 'gate');
    }

    private function effectiveMediaState(
        EventMedia $media,
        string $safetyDecision,
        string $contextDecision,
        string $operatorDecision,
        string $publicationDecision,
        bool $safetyIsBlocking,
        bool $contextIsBlocking,
    ): string {
        if ($operatorDecision === 'rejected') {
            return 'rejected';
        }

        if ($safetyIsBlocking && $safetyDecision === 'rejected') {
            return 'rejected';
        }

        if ($contextIsBlocking && $contextDecision === 'rejected') {
            return 'rejected';
        }

        if (
            ($safetyIsBlocking && in_array($safetyDecision, ['pending', 'review', 'failed'], true))
            || ($contextIsBlocking && in_array($contextDecision, ['pending', 'review', 'failed'], true))
            || $media->moderation_status === ModerationStatus::Pending
        ) {
            return 'pending_moderation';
        }

        if ($media->moderation_status === ModerationStatus::Rejected) {
            return 'rejected';
        }

        if ($publicationDecision === PublicationStatus::Hidden->value) {
            return 'hidden';
        }

        if (
            $publicationDecision === PublicationStatus::Published->value
            && $media->moderation_status === ModerationStatus::Approved
        ) {
            return 'published';
        }

        if ($media->moderation_status === ModerationStatus::Approved) {
            return 'approved';
        }

        return match ($media->processing_status) {
            MediaProcessingStatus::Failed => 'error',
            MediaProcessingStatus::Downloaded, MediaProcessingStatus::Processed => 'processing',
            default => 'received',
        };
    }
}
