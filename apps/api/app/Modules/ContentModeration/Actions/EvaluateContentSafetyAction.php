<?php

namespace App\Modules\ContentModeration\Actions;

use App\Modules\ContentModeration\DTOs\ContentSafetyEvaluationResult;
use App\Modules\ContentModeration\Models\EventMediaSafetyEvaluation;
use App\Modules\ContentModeration\Services\ContentModerationProviderInterface;
use App\Modules\ContentModeration\Services\ContentModerationPolicySnapshotFactory;
use App\Modules\ContentModeration\Services\ContentModerationSettingsResolver;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Shared\Support\NormalizedTextContextBuilder;

class EvaluateContentSafetyAction
{
    public function __construct(
        private readonly ContentModerationProviderInterface $provider,
        private readonly ContentModerationPolicySnapshotFactory $policySnapshots,
        private readonly ContentModerationSettingsResolver $settingsResolver,
        private readonly NormalizedTextContextBuilder $textContexts,
    ) {}

    public function execute(EventMedia $media): ContentSafetyEvaluationResult
    {
        $media->loadMissing('event.contentModerationSettings');
        $settings = $media->event
            ? $this->settingsResolver->resolveForEvent($media->event)
            : null;

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
        $execution = (array) data_get($result->rawResponse, 'execution', []);
        $runtimeOverrides = [
            'provider_version' => $result->providerVersion,
            'model_key' => $result->modelKey,
            'model_snapshot' => $result->modelSnapshot,
        ];

        if (data_get($execution, 'fallback_from')) {
            $runtimeOverrides['provider_key'] = $result->providerKey;
        }

        $policy = $this->policySnapshots->build($settings, $runtimeOverrides);
        $normalizedContext = $this->textContexts->build(
            (string) ($settings?->normalized_text_context_mode ?? 'body_plus_caption'),
            caption: $media->caption,
            bodyText: $media->inboundMessage?->body_text,
            operatorSummary: null,
        );
        $evaluationAttributes = $result->toEvaluationAttributes();
        $evaluationAttributes['normalized_text_context'] = $result->normalizedTextContext ?? $normalizedContext['text'];
        $evaluationAttributes['normalized_text_context_mode'] = $result->normalizedTextContextMode ?? $normalizedContext['mode'];

        EventMediaSafetyEvaluation::query()->create(array_merge(
            $evaluationAttributes,
            [
                'event_id' => $media->event_id,
                'event_media_id' => $media->id,
                'policy_snapshot_json' => $policy['snapshot'],
                'policy_sources_json' => $policy['sources'],
                'completed_at' => now(),
            ],
        ));

        return $result;
    }
}
