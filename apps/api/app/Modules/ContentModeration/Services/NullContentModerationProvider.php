<?php

namespace App\Modules\ContentModeration\Services;

use App\Modules\ContentModeration\DTOs\ContentSafetyEvaluationResult;
use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\MediaProcessing\Models\EventMedia;

class NullContentModerationProvider implements ContentModerationProviderInterface
{
    public function evaluate(
        EventMedia $media,
        EventContentModerationSetting $settings,
    ): ContentSafetyEvaluationResult {
        return ContentSafetyEvaluationResult::pass(
            categoryScores: [
                'nudity' => 0.0,
                'violence' => 0.0,
            ],
            rawResponse: [
                'provider' => 'noop',
                'message' => 'Foundation provider for local pipeline hardening.',
                'event_media_id' => $media->id,
            ],
            providerKey: $settings->provider_key ?: 'noop',
            providerVersion: 'foundation-v1',
            modelKey: 'noop-safety-v1',
            modelSnapshot: 'noop-safety-v1',
            thresholdVersion: $settings->threshold_version,
        );
    }
}
