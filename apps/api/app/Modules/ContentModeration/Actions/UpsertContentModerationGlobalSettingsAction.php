<?php

namespace App\Modules\ContentModeration\Actions;

use App\Modules\ContentModeration\Models\ContentModerationGlobalSetting;
use App\Modules\ContentModeration\Models\EventContentModerationSetting;

class UpsertContentModerationGlobalSettingsAction
{
    /**
     * @param array<string, mixed> $payload
     */
    public function execute(array $payload): ContentModerationGlobalSetting
    {
        $settings = ContentModerationGlobalSetting::query()->firstOrNew(
            ['id' => 1],
            ContentModerationGlobalSetting::defaultAttributes(),
        );

        $defaults = EventContentModerationSetting::defaultAttributes();

        $settings->fill([
            'provider_key' => (string) ($payload['provider_key'] ?? $settings->provider_key ?? $defaults['provider_key']),
            'mode' => (string) ($payload['mode'] ?? $settings->mode ?? $defaults['mode']),
            'threshold_version' => (string) ($payload['threshold_version'] ?? $settings->threshold_version ?? $defaults['threshold_version']),
            'fallback_mode' => (string) ($payload['fallback_mode'] ?? $settings->fallback_mode ?? $defaults['fallback_mode']),
            'analysis_scope' => (string) ($payload['analysis_scope'] ?? $payload['objective_safety_scope'] ?? $settings->analysis_scope ?? $defaults['analysis_scope']),
            'normalized_text_context_mode' => (string) ($payload['normalized_text_context_mode'] ?? $settings->normalized_text_context_mode ?? $defaults['normalized_text_context_mode']),
            'enabled' => (bool) ($payload['enabled'] ?? $settings->enabled ?? $defaults['enabled']),
            'hard_block_thresholds_json' => $this->normalizeThresholds(
                $payload['hard_block_thresholds'] ?? $settings->hard_block_thresholds_json ?? $defaults['hard_block_thresholds_json'],
                $defaults['hard_block_thresholds_json'],
            ),
            'review_thresholds_json' => $this->normalizeThresholds(
                $payload['review_thresholds'] ?? $settings->review_thresholds_json ?? $defaults['review_thresholds_json'],
                $defaults['review_thresholds_json'],
            ),
        ]);

        $settings->save();

        return $settings->refresh();
    }

    /**
     * @param array<string, mixed> $thresholds
     * @param array<string, mixed> $defaults
     * @return array<string, float>
     */
    private function normalizeThresholds(array $thresholds, array $defaults): array
    {
        $normalized = [];

        foreach ($defaults as $key => $defaultValue) {
            $value = $thresholds[$key] ?? $defaultValue;

            if (! is_numeric($value)) {
                $normalized[$key] = (float) $defaultValue;
                continue;
            }

            $normalized[$key] = max(0.0, min(1.0, round((float) $value, 6)));
        }

        return $normalized;
    }
}
