<?php

namespace App\Modules\MediaIntelligence\Actions;

use App\Modules\MediaIntelligence\DTOs\VisualReasoningEvaluationResult;
use App\Modules\MediaIntelligence\Models\EventMediaVlmEvaluation;
use App\Modules\MediaIntelligence\Services\VisualReasoningProviderInterface;
use App\Modules\MediaProcessing\Models\EventMedia;

class EvaluateMediaPromptAction
{
    public function __construct(
        private readonly VisualReasoningProviderInterface $provider,
    ) {}

    public function execute(EventMedia $media): VisualReasoningEvaluationResult
    {
        $media->loadMissing('event.mediaIntelligenceSettings', 'variants', 'inboundMessage');
        $settings = $media->event?->mediaIntelligenceSettings;

        if ($media->media_type !== 'image') {
            return VisualReasoningEvaluationResult::skipped(
                providerKey: 'noop',
                providerVersion: 'foundation-v1',
                modelKey: 'noop-vlm-v1',
                modelSnapshot: 'noop-vlm-v1',
                promptVersion: $settings?->prompt_version,
                responseSchemaVersion: $settings?->response_schema_version,
                modeApplied: $settings?->mode,
            );
        }

        if (! $settings || ! $settings->enabled) {
            return VisualReasoningEvaluationResult::skipped(
                providerKey: $settings?->provider_key ?? 'noop',
                providerVersion: 'foundation-v1',
                modelKey: 'noop-vlm-v1',
                modelSnapshot: 'noop-vlm-v1',
                promptVersion: $settings?->prompt_version,
                responseSchemaVersion: $settings?->response_schema_version,
                modeApplied: $settings?->mode,
            );
        }

        $result = $this->provider->evaluate($media, $settings);

        EventMediaVlmEvaluation::query()->create(array_merge(
            $result->toEvaluationAttributes(),
            [
                'event_id' => $media->event_id,
                'event_media_id' => $media->id,
                'completed_at' => now(),
            ],
        ));

        return $result;
    }
}
