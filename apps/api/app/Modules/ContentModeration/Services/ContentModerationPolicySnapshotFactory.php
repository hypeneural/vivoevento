<?php

namespace App\Modules\ContentModeration\Services;

use App\Modules\ContentModeration\Models\EventContentModerationSetting;

class ContentModerationPolicySnapshotFactory
{
    /**
     * @param array<string, mixed> $runtimeOverrides
     * @return array{snapshot: array<string, mixed>, sources: array<string, string>}
     */
    public function build(?EventContentModerationSetting $settings, array $runtimeOverrides = []): array
    {
        $defaults = EventContentModerationSetting::defaultAttributes();
        $resolvedProviderKey = $this->resolveValue($settings, 'provider_key', $defaults);
        $resolvedEnabled = $this->resolveValue($settings, 'enabled', $defaults);
        $resolvedMode = $this->resolveValue($settings, 'mode', $defaults);
        $resolvedFallbackMode = $this->resolveValue($settings, 'fallback_mode', $defaults);
        $resolvedAnalysisScope = $this->resolveValue($settings, 'analysis_scope', $defaults);
        $resolvedNormalizedTextContextMode = $this->resolveValue($settings, 'normalized_text_context_mode', $defaults);
        $resolvedThresholdVersion = $this->resolveValue($settings, 'threshold_version', $defaults);
        $resolvedHardBlockThresholds = $this->resolveValue($settings, 'hard_block_thresholds_json', $defaults);
        $resolvedReviewThresholds = $this->resolveValue($settings, 'review_thresholds_json', $defaults);

        $snapshot = [
            'provider_key' => $runtimeOverrides['provider_key'] ?? $resolvedProviderKey,
            'enabled' => $runtimeOverrides['enabled'] ?? $resolvedEnabled,
            'mode' => $runtimeOverrides['mode'] ?? $resolvedMode,
            'fallback_mode' => $runtimeOverrides['fallback_mode'] ?? $resolvedFallbackMode,
            'analysis_scope' => $runtimeOverrides['analysis_scope'] ?? $resolvedAnalysisScope,
            'objective_safety_scope' => $runtimeOverrides['analysis_scope'] ?? $resolvedAnalysisScope,
            'normalized_text_context_mode' => $runtimeOverrides['normalized_text_context_mode'] ?? $resolvedNormalizedTextContextMode,
            'threshold_version' => $runtimeOverrides['threshold_version'] ?? $resolvedThresholdVersion,
            'hard_block_thresholds' => $runtimeOverrides['hard_block_thresholds_json'] ?? $resolvedHardBlockThresholds,
            'review_thresholds' => $runtimeOverrides['review_thresholds_json'] ?? $resolvedReviewThresholds,
            'provider_version' => $runtimeOverrides['provider_version'] ?? null,
            'model_key' => $runtimeOverrides['model_key'] ?? null,
            'model_snapshot' => $runtimeOverrides['model_snapshot'] ?? null,
        ];

        $sources = [
            'provider_key' => $this->resolveOverrideSource(
                $settings,
                'provider_key',
                $runtimeOverrides,
                $resolvedProviderKey,
            ),
            'enabled' => $this->resolveOverrideSource($settings, 'enabled', $runtimeOverrides, $resolvedEnabled),
            'mode' => $this->resolveOverrideSource($settings, 'mode', $runtimeOverrides, $resolvedMode),
            'fallback_mode' => $this->resolveOverrideSource($settings, 'fallback_mode', $runtimeOverrides, $resolvedFallbackMode),
            'analysis_scope' => $this->resolveOverrideSource($settings, 'analysis_scope', $runtimeOverrides, $resolvedAnalysisScope),
            'objective_safety_scope' => $this->resolveOverrideSource($settings, 'analysis_scope', $runtimeOverrides, $resolvedAnalysisScope),
            'normalized_text_context_mode' => $this->resolveOverrideSource($settings, 'normalized_text_context_mode', $runtimeOverrides, $resolvedNormalizedTextContextMode),
            'threshold_version' => $this->resolveOverrideSource($settings, 'threshold_version', $runtimeOverrides, $resolvedThresholdVersion),
            'hard_block_thresholds' => $this->resolveOverrideSource($settings, 'hard_block_thresholds_json', $runtimeOverrides, $resolvedHardBlockThresholds),
            'review_thresholds' => $this->resolveOverrideSource($settings, 'review_thresholds_json', $runtimeOverrides, $resolvedReviewThresholds),
            'provider_version' => array_key_exists('provider_version', $runtimeOverrides) ? 'runtime_fallback' : 'provider_runtime',
            'model_key' => array_key_exists('model_key', $runtimeOverrides) ? 'runtime_fallback' : 'provider_runtime',
            'model_snapshot' => array_key_exists('model_snapshot', $runtimeOverrides) ? 'runtime_fallback' : 'provider_runtime',
        ];

        return [
            'snapshot' => $snapshot,
            'sources' => $sources,
        ];
    }

    /**
     * @param array<string, mixed> $defaults
     */
    private function resolveValue(?EventContentModerationSetting $settings, string $attribute, array $defaults): mixed
    {
        if ($settings && $settings->getAttribute($attribute) !== null) {
            return $settings->getAttribute($attribute);
        }

        return $defaults[$attribute] ?? null;
    }

    private function resolveSource(?EventContentModerationSetting $settings, string $attribute): string
    {
        if ((bool) ($settings?->inherits_global ?? false)) {
            return 'global_setting';
        }

        return $settings && $settings->getAttribute($attribute) !== null
            ? 'event_setting'
            : 'default_config';
    }

    /**
     * @param array<string, mixed> $runtimeOverrides
     */
    private function resolveOverrideSource(
        ?EventContentModerationSetting $settings,
        string $attribute,
        array $runtimeOverrides,
        mixed $resolvedValue,
    ): string {
        if (! array_key_exists($attribute, $runtimeOverrides) || $runtimeOverrides[$attribute] === $resolvedValue) {
            return $this->resolveSource($settings, $attribute);
        }

        return 'runtime_fallback';
    }
}
