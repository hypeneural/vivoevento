<?php

namespace App\Modules\ContentModeration\Actions;

use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\Events\Models\Event;

class UpsertEventContentModerationSettingsAction
{
    /**
     * @param array<string, mixed> $payload
     */
    public function execute(Event $event, array $payload): EventContentModerationSetting
    {
        $defaults = EventContentModerationSetting::defaultAttributes();
        $current = $event->contentModerationSettings;

        $hardBlockThresholds = $this->normalizeThresholds(
            $payload['hard_block_thresholds'] ?? $current?->hard_block_thresholds_json ?? $defaults['hard_block_thresholds_json'],
            $defaults['hard_block_thresholds_json'],
        );

        $reviewThresholds = $this->normalizeThresholds(
            $payload['review_thresholds'] ?? $current?->review_thresholds_json ?? $defaults['review_thresholds_json'],
            $defaults['review_thresholds_json'],
        );

        return EventContentModerationSetting::query()->updateOrCreate(
            [
                'event_id' => $event->id,
            ],
            [
                'provider_key' => (string) ($payload['provider_key'] ?? $current?->provider_key ?? $defaults['provider_key']),
                'mode' => (string) ($payload['mode'] ?? $current?->mode ?? $defaults['mode']),
                'threshold_version' => (string) ($payload['threshold_version'] ?? $current?->threshold_version ?? $defaults['threshold_version']),
                'hard_block_thresholds_json' => $hardBlockThresholds,
                'review_thresholds_json' => $reviewThresholds,
                'fallback_mode' => (string) ($payload['fallback_mode'] ?? $current?->fallback_mode ?? $defaults['fallback_mode']),
                'enabled' => (bool) ($payload['enabled'] ?? $current?->enabled ?? $defaults['enabled']),
            ],
        );
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
