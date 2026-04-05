<?php

namespace App\Modules\ContentModeration\Actions;

use App\Modules\ContentModeration\DTOs\ContentSafetyEvaluationResult;
use App\Modules\ContentModeration\Models\EventMediaSafetyEvaluation;
use App\Modules\ContentModeration\Services\ContentModerationProviderInterface;
use App\Modules\MediaProcessing\Models\EventMedia;

class EvaluateContentSafetyAction
{
    public function __construct(
        private readonly ContentModerationProviderInterface $provider,
    ) {}

    public function execute(EventMedia $media): ContentSafetyEvaluationResult
    {
        $media->loadMissing('event.contentModerationSettings');
        $settings = $media->event?->contentModerationSettings;

        if ($media->media_type !== 'image') {
            return ContentSafetyEvaluationResult::skipped(
                providerKey: 'noop',
                providerVersion: 'foundation-v1',
                modelKey: 'noop-safety-v1',
                modelSnapshot: 'noop-safety-v1',
            );
        }

        if (! $media->event?->isAiModeration()) {
            return ContentSafetyEvaluationResult::skipped(
                providerKey: $settings?->provider_key ?? 'noop',
                providerVersion: 'foundation-v1',
                modelKey: 'noop-safety-v1',
                modelSnapshot: 'noop-safety-v1',
                thresholdVersion: $settings?->threshold_version,
            );
        }

        if (! $settings || ! $settings->enabled) {
            return ContentSafetyEvaluationResult::skipped(
                providerKey: $settings?->provider_key ?? 'noop',
                providerVersion: 'foundation-v1',
                modelKey: 'noop-safety-v1',
                modelSnapshot: 'noop-safety-v1',
                thresholdVersion: $settings?->threshold_version,
            );
        }

        $result = $this->provider->evaluate($media, $settings);

        EventMediaSafetyEvaluation::query()->create(array_merge(
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
