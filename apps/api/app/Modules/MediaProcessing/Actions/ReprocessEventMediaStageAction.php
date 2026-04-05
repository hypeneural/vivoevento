<?php

namespace App\Modules\MediaProcessing\Actions;

use App\Modules\ContentModeration\Jobs\AnalyzeContentSafetyJob;
use App\Modules\FaceSearch\Jobs\IndexMediaFacesJob;
use App\Modules\MediaIntelligence\Jobs\EvaluateMediaPromptJob;
use App\Modules\MediaProcessing\Enums\MediaReprocessStage;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaAuditLogger;
use App\Modules\MediaProcessing\Services\MediaPipelineDegradationPolicy;
use App\Modules\MediaProcessing\Services\ModerationBroadcasterService;
use App\Modules\Users\Models\User;
use InvalidArgumentException;

class ReprocessEventMediaStageAction
{
    public function __construct(
        private readonly MediaAuditLogger $auditLogger,
        private readonly ModerationBroadcasterService $broadcaster,
        private readonly MediaPipelineDegradationPolicy $degradationPolicy,
    ) {}

    public function execute(
        EventMedia $eventMedia,
        MediaReprocessStage $stage,
        User $actor,
        ?string $reason = null,
    ): EventMedia {
        $eventMedia->loadMissing(['event', 'variants', 'inboundMessage']);

        $old = [
            'safety_status' => $eventMedia->safety_status,
            'vlm_status' => $eventMedia->vlm_status,
            'face_index_status' => $eventMedia->face_index_status,
        ];

        match ($stage) {
            MediaReprocessStage::Safety => $this->queueSafety($eventMedia),
            MediaReprocessStage::Vlm => $this->queueVlm($eventMedia),
            MediaReprocessStage::FaceIndex => $this->queueFaceIndex($eventMedia),
        };

        $this->auditLogger->log(
            actor: $actor,
            eventMedia: $eventMedia,
            event: 'media.reprocess_requested',
            description: 'Reprocessamento solicitado',
            old: $old,
            attributes: [
                'stage' => $stage->value,
                'reason' => $reason,
            ],
            context: [
                'reprocess_stage' => $stage->value,
            ],
        );

        $this->broadcaster->broadcastUpdated($eventMedia->fresh(['event', 'variants', 'inboundMessage']));

        return $eventMedia->refresh();
    }

    private function queueSafety(EventMedia $eventMedia): void
    {
        $eventMedia->forceFill([
            'safety_status' => 'queued',
            'last_pipeline_error_code' => null,
            'last_pipeline_error_message' => null,
        ])->save();

        AnalyzeContentSafetyJob::dispatch($eventMedia->id);
    }

    private function queueVlm(EventMedia $eventMedia): void
    {
        if (! $this->degradationPolicy->vlmEnabled()) {
            $eventMedia->forceFill([
                'vlm_status' => 'skipped',
                'last_pipeline_error_code' => null,
                'last_pipeline_error_message' => null,
            ])->save();

            return;
        }

        $eventMedia->forceFill([
            'vlm_status' => 'queued',
            'last_pipeline_error_code' => null,
            'last_pipeline_error_message' => null,
        ])->save();

        EvaluateMediaPromptJob::dispatch($eventMedia->id);
    }

    private function queueFaceIndex(EventMedia $eventMedia): void
    {
        if (! $eventMedia->event?->faceSearchSettings?->enabled) {
            throw new InvalidArgumentException('FaceSearch nao esta habilitado para este evento.');
        }

        if (! $this->degradationPolicy->faceIndexEnabled()) {
            $eventMedia->forceFill([
                'face_index_status' => 'skipped',
                'last_pipeline_error_code' => null,
                'last_pipeline_error_message' => null,
            ])->save();

            return;
        }

        $eventMedia->forceFill([
            'face_index_status' => 'queued',
            'last_pipeline_error_code' => null,
            'last_pipeline_error_message' => null,
        ])->save();

        IndexMediaFacesJob::dispatch($eventMedia->id);
    }
}
