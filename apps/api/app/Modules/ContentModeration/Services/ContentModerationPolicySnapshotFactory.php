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

        $snapshot = [
            'provider_key' => $runtimeOverrides['provider_key'] ?? $resolvedProviderKey,
            'enabled' => $this->resolveValue($settings, 'enabled', $defaults),
            'mode' => $this->resolveValue($settings, 'mode', $defaults),
            'fallback_mode' => $this->resolveValue($settings, 'fallback_mode', $defaults),
            'analysis_scope' => $this->resolveValue($settings, 'analysis_scope', $defaults),
            'objective_safety_scope' => $this->resolveValue($settings, 'analysis_scope', $defaults),
            'normalized_text_context_mode' => $this->resolveValue($settings, 'normalized_text_context_mode', $defaults),
            'threshold_version' => $this->resolveValue($settings, 'threshold_version', $defaults),
            'hard_block_thresholds' => $this->resolveValue($settings, 'hard_block_thresholds_json', $defaults),
            'review_thresholds' => $this->resolveValue($settings, 'review_thresholds_json', $defaults),
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
            'enabled' => $this->resolveSource($settings, 'enabled'),
            'mode' => $this->resolveSource($settings, 'mode'),
            'fallback_mode' => $this->resolveSource($settings, 'fallback_mode'),
            'analysis_scope' => $this->resolveSource($settings, 'analysis_scope'),
            'objective_safety_scope' => $this->resolveSource($settings, 'analysis_scope'),
            'normalized_text_context_mode' => $this->resolveSource($settings, 'normalized_text_context_mode'),
            'threshold_version' => $this->resolveSource($settings, 'threshold_version'),
            'hard_block_thresholds' => $this->resolveSource($settings, 'hard_block_thresholds_json'),
            'review_thresholds' => $this->resolveSource($settings, 'review_thresholds_json'),
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
