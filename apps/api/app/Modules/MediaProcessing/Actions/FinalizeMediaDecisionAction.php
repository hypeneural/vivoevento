<?php

namespace App\Modules\MediaProcessing\Actions;

use App\Modules\MediaProcessing\Enums\MediaDecisionSource;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\DB;

class FinalizeMediaDecisionAction
{
    public function execute(EventMedia $media): EventMedia
    {
        return DB::transaction(function () use ($media): EventMedia {
            $lockedMedia = EventMedia::query()
                ->whereKey($media->getKey())
                ->lockForUpdate()
                ->with(['event.faceSearchSettings', 'event.mediaIntelligenceSettings', 'event.contentModerationSettings'])
                ->first();

            if (! $lockedMedia || ! $lockedMedia->event) {
                return $media->fresh(['event', 'variants', 'inboundMessage']) ?? $media;
            }

            $updates = $this->normalizedStatuses($lockedMedia);
            $nextModerationStatus = $this->nextModerationStatus($lockedMedia, $updates);
            $nextDecisionSource = $this->nextDecisionSource($lockedMedia, $updates);

            if ($nextModerationStatus && $lockedMedia->moderation_status !== $nextModerationStatus) {
                $updates['moderation_status'] = $nextModerationStatus;
            }

            if ($nextDecisionSource && $lockedMedia->decision_source !== $nextDecisionSource) {
                $updates['decision_source'] = $nextDecisionSource;
            }

            if ($updates !== []) {
                $lockedMedia->forceFill($updates)->save();
            }

            return $lockedMedia->fresh(['event', 'variants', 'inboundMessage']);
        }, 3);
    }

    private function normalizedStatuses(EventMedia $media): array
    {
        $updates = [];

        if (
            ($media->safety_status === null || $media->safety_status === 'queued')
            && $media->media_type === 'image'
            && ! $media->event?->isAiModeration()
        ) {
            $updates['safety_status'] = 'skipped';
        }

        if (
            ($media->vlm_status === null || $media->vlm_status === 'queued')
            && $media->media_type === 'image'
            && ! $this->shouldWaitForVlmGate($media)
        ) {
            $updates['vlm_status'] = 'skipped';
        }

        if ($media->face_index_status === null) {
            $updates['face_index_status'] = $this->shouldQueueFaceIndex($media) ? 'queued' : 'skipped';
        }

        if ($media->pipeline_version === null) {
            $updates['pipeline_version'] = 'media_ai_foundation_v1';
        }

        if ($media->last_pipeline_error_code !== null || $media->last_pipeline_error_message !== null) {
            $updates['last_pipeline_error_code'] = null;
            $updates['last_pipeline_error_message'] = null;
        }

        return $updates;
    }

    private function shouldQueueFaceIndex(EventMedia $media): bool
    {
        return $media->media_type === 'image'
            && app(\App\Modules\MediaProcessing\Services\MediaPipelineDegradationPolicy::class)->faceIndexEnabled()
            && $media->event?->isFaceSearchEnabled();
    }

    /**
     * @param array<string, mixed> $updates
     */
    private function nextModerationStatus(EventMedia $media, array $updates): ?ModerationStatus
    {
        $safetyStatus = $this->effectiveSafetyStatus(
            $media,
            $updates['safety_status'] ?? $media->safety_status,
        );
        $vlmStatus = $updates['vlm_status'] ?? $media->vlm_status;

        if ($media->moderation_status === ModerationStatus::Rejected) {
            return ModerationStatus::Rejected;
        }

        if ($media->moderation_status === ModerationStatus::Approved) {
            return ModerationStatus::Approved;
        }

        if (in_array($safetyStatus, ['failed'], true) || in_array($vlmStatus, ['failed'], true)) {
            return ModerationStatus::Pending;
        }

        if ($media->event->isNoModeration()) {
            return ModerationStatus::Approved;
        }

        if ($media->event->isManualModeration()) {
            return ModerationStatus::Pending;
        }

        if ($safetyStatus === 'block') {
            return ModerationStatus::Rejected;
        }

        if (in_array($safetyStatus, [null, 'queued', 'review', 'failed'], true)) {
            return ModerationStatus::Pending;
        }

        if ($this->shouldWaitForVlmGate($media) && in_array($vlmStatus, [null, 'queued'], true)) {
            return ModerationStatus::Pending;
        }

        if (in_array($vlmStatus, ['review', 'failed', 'rejected'], true)) {
            return ModerationStatus::Pending;
        }

        return ModerationStatus::Approved;
    }

    /**
     * @param array<string, mixed> $updates
     */
    private function nextDecisionSource(EventMedia $media, array $updates): ?MediaDecisionSource
    {
        $safetyStatus = $this->effectiveSafetyStatus(
            $media,
            $updates['safety_status'] ?? $media->safety_status,
        );
        $vlmStatus = $updates['vlm_status'] ?? $media->vlm_status;

        if (
            $media->moderation_status === ModerationStatus::Rejected
            || $media->moderation_status === ModerationStatus::Approved
        ) {
            return $media->decision_source;
        }

        if ($media->event->isNoModeration()) {
            return MediaDecisionSource::NoneMode;
        }

        if ($media->event->isManualModeration()) {
            return MediaDecisionSource::ManualReview;
        }

        if (
            $this->shouldWaitForVlmGate($media)
            && in_array($vlmStatus, [null, 'queued', 'review', 'failed', 'rejected'], true)
            && $safetyStatus === 'pass'
        ) {
            return MediaDecisionSource::AiVlm;
        }

        if (in_array($vlmStatus, ['review', 'failed', 'rejected'], true) && $safetyStatus === 'pass') {
            return MediaDecisionSource::AiVlm;
        }

        return MediaDecisionSource::AiSafety;
    }

    private function shouldWaitForVlmGate(EventMedia $media): bool
    {
        return $media->media_type === 'image'
            && app(\App\Modules\MediaProcessing\Services\MediaPipelineDegradationPolicy::class)->vlmEnabled()
            && $media->event?->isAiModeration()
            && (bool) ($media->event?->mediaIntelligenceSettings?->enabled ?? false)
            && ($media->event?->mediaIntelligenceSettings?->mode === 'gate')
            && in_array($this->effectiveSafetyStatus($media, $media->safety_status), ['pass', 'skipped'], true);
    }

    private function effectiveSafetyStatus(EventMedia $media, ?string $safetyStatus): ?string
    {
        if (
            $media->event?->isContentModerationObserveOnly()
            && ! in_array($safetyStatus, [null, 'queued'], true)
        ) {
            return 'pass';
        }

        return $safetyStatus;
    }
}
