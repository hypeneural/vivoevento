<?php

namespace App\Modules\MediaIntelligence\Actions;

use App\Modules\MediaIntelligence\DTOs\VisualReasoningEvaluationResult;
use App\Modules\MediaIntelligence\Models\EventMediaVlmEvaluation;
use App\Modules\MediaIntelligence\Services\ContextualModerationPolicyResolver;
use App\Modules\MediaIntelligence\Services\OpenAiCompatibleVisualReasoningPayloadFactory;
use App\Modules\MediaIntelligence\Services\VisualReasoningProviderInterface;
use App\Modules\MediaProcessing\Models\EventMedia;

class EvaluateMediaPromptAction
{
    public function __construct(
        private readonly VisualReasoningProviderInterface $provider,
        private readonly ContextualModerationPolicyResolver $policyResolver,
        private readonly OpenAiCompatibleVisualReasoningPayloadFactory $payloadFactory,
    ) {}

    public function execute(EventMedia $media): VisualReasoningEvaluationResult
    {
        $media->loadMissing('event.mediaIntelligenceSettings', 'variants', 'inboundMessage');
        $resolvedPolicy = $media->event
            ? $this->policyResolver->resolveForEvent($media->event)
            : null;
        /** @var \App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting|null $settings */
        $settings = $resolvedPolicy['settings'] ?? null;

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
        $resolvedPromptContext = array_merge(
            $settings ? ($this->payloadFactory->promptContext($media, $settings) ?? []) : [],
            $result->promptContext ?? [],
            [
                'policy_snapshot' => $resolvedPolicy['snapshot'] ?? [],
                'policy_sources' => $resolvedPolicy['sources'] ?? [],
            ],
        );
        $execution = (array) data_get($result->rawResponse, 'execution', []);
        $runtimeOverrides = [
            'provider_version' => $result->providerVersion,
            'model_snapshot' => $result->modelSnapshot,
        ];

        if (data_get($execution, 'fallback_from')) {
            $runtimeOverrides['provider_key'] = $result->providerKey;
            $runtimeOverrides['model_key'] = $result->modelKey;
        }

        $policy = $media->event
            ? $this->policyResolver->resolveForEvent($media->event->fresh('mediaIntelligenceSettings'), $runtimeOverrides)
            : $resolvedPolicy;
        $evaluationAttributes = $result->toEvaluationAttributes();
        $evaluationAttributes['prompt_context_json'] = $resolvedPromptContext !== [] ? $resolvedPromptContext : null;
        $evaluationAttributes['normalized_text_context'] = $result->normalizedTextContext
            ?? data_get($resolvedPromptContext, 'normalized_text_context');
        $evaluationAttributes['normalized_text_context_mode'] = $result->normalizedTextContextMode
            ?? data_get($resolvedPromptContext, 'normalized_text_context_mode');

        EventMediaVlmEvaluation::query()->create(array_merge(
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
