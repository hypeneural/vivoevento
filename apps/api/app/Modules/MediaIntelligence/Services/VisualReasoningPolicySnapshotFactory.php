<?php

namespace App\Modules\MediaIntelligence\Services;

use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;

class VisualReasoningPolicySnapshotFactory
{
    /**
     * @param array<string, mixed> $runtimeOverrides
     * @return array{snapshot: array<string, mixed>, sources: array<string, string>}
     */
    public function build(?EventMediaIntelligenceSetting $settings, array $runtimeOverrides = []): array
    {
        $defaults = EventMediaIntelligenceSetting::defaultAttributes();
        $resolvedProviderKey = $this->resolveValue($settings, 'provider_key', $defaults);
        $resolvedModelKey = $this->resolveValue($settings, 'model_key', $defaults);

        $snapshot = [
            'provider_key' => $runtimeOverrides['provider_key'] ?? $resolvedProviderKey,
            'model_key' => $runtimeOverrides['model_key'] ?? $resolvedModelKey,
            'model_snapshot' => $runtimeOverrides['model_snapshot'] ?? null,
            'enabled' => $this->resolveValue($settings, 'enabled', $defaults),
            'mode' => $this->resolveValue($settings, 'mode', $defaults),
            'fallback_mode' => $this->resolveValue($settings, 'fallback_mode', $defaults),
            'context_scope' => $this->resolveValue($settings, 'context_scope', $defaults),
            'reply_scope' => $this->resolveValue($settings, 'reply_scope', $defaults),
            'normalized_text_context_mode' => $this->resolveValue($settings, 'normalized_text_context_mode', $defaults),
            'require_json_output' => $this->resolveValue($settings, 'require_json_output', $defaults),
            'prompt_version' => $this->resolveValue($settings, 'prompt_version', $defaults),
            'response_schema_version' => $this->resolveValue($settings, 'response_schema_version', $defaults),
            'approval_prompt' => $this->resolveValue($settings, 'approval_prompt', $defaults),
            'caption_style_prompt' => $this->resolveValue($settings, 'caption_style_prompt', $defaults),
            'provider_version' => $runtimeOverrides['provider_version'] ?? null,
        ];

        $sources = [
            'provider_key' => $this->resolveOverrideSource(
                $settings,
                'provider_key',
                $runtimeOverrides,
                $resolvedProviderKey,
            ),
            'model_key' => $this->resolveOverrideSource(
                $settings,
                'model_key',
                $runtimeOverrides,
                $resolvedModelKey,
            ),
            'model_snapshot' => array_key_exists('model_snapshot', $runtimeOverrides) ? 'runtime_fallback' : 'provider_runtime',
            'enabled' => $this->resolveSource($settings, 'enabled'),
            'mode' => $this->resolveSource($settings, 'mode'),
            'fallback_mode' => $this->resolveSource($settings, 'fallback_mode'),
            'context_scope' => $this->resolveSource($settings, 'context_scope'),
            'reply_scope' => $this->resolveSource($settings, 'reply_scope'),
            'normalized_text_context_mode' => $this->resolveSource($settings, 'normalized_text_context_mode'),
            'require_json_output' => $this->resolveSource($settings, 'require_json_output'),
            'prompt_version' => $this->resolveSource($settings, 'prompt_version'),
            'response_schema_version' => $this->resolveSource($settings, 'response_schema_version'),
            'approval_prompt' => $this->resolveSource($settings, 'approval_prompt'),
            'caption_style_prompt' => $this->resolveSource($settings, 'caption_style_prompt'),
            'provider_version' => array_key_exists('provider_version', $runtimeOverrides) ? 'runtime_fallback' : 'provider_runtime',
        ];

        return [
            'snapshot' => $snapshot,
            'sources' => $sources,
        ];
    }

    /**
     * @param array<string, mixed> $defaults
     */
    private function resolveValue(?EventMediaIntelligenceSetting $settings, string $attribute, array $defaults): mixed
    {
        if ($settings && $settings->getAttribute($attribute) !== null) {
            return $settings->getAttribute($attribute);
        }

        return $defaults[$attribute] ?? null;
    }

    private function resolveSource(?EventMediaIntelligenceSetting $settings, string $attribute): string
    {
        return $settings && $settings->getAttribute($attribute) !== null
            ? 'event_setting'
            : 'default_config';
    }

    /**
     * @param array<string, mixed> $runtimeOverrides
     */
    private function resolveOverrideSource(
        ?EventMediaIntelligenceSetting $settings,
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
