<?php

namespace App\Modules\MediaIntelligence\Services;

use App\Modules\MediaIntelligence\DTOs\VisualReasoningEvaluationResult;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaProcessing\Models\EventMedia;

class NullVisualReasoningProvider implements VisualReasoningProviderInterface
{
    public function evaluate(
        EventMedia $media,
        EventMediaIntelligenceSetting $settings,
    ): VisualReasoningEvaluationResult {
        return VisualReasoningEvaluationResult::approve(
            reason: 'Provider noop executado sem inferencia externa.',
            shortCaption: $media->caption,
            tags: [],
            rawResponse: [
                'provider' => 'noop',
            ],
            providerKey: 'noop',
            providerVersion: (string) config('media_intelligence.providers.noop.provider_version', 'foundation-v1'),
            modelKey: (string) config('media_intelligence.providers.noop.model', 'noop-vlm-v1'),
            modelSnapshot: (string) config('media_intelligence.providers.noop.model_snapshot', 'noop-vlm-v1'),
            promptVersion: $settings->prompt_version,
            responseSchemaVersion: $settings->response_schema_version,
            modeApplied: $settings->mode,
        );
    }
}
