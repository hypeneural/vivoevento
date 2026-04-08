<?php

namespace App\Modules\MediaIntelligence\Actions;

use App\Modules\MediaIntelligence\Models\MediaIntelligenceGlobalSetting;

class UpsertMediaIntelligenceGlobalSettingsAction
{
    /**
     * @param array<string, mixed> $payload
     */
    public function execute(array $payload): MediaIntelligenceGlobalSetting
    {
        $settings = MediaIntelligenceGlobalSetting::query()->firstOrNew(
            ['id' => 1],
            MediaIntelligenceGlobalSetting::defaultAttributes(),
        );

        $settings->fill([
            'reply_text_prompt' => (string) ($payload['reply_text_prompt'] ?? MediaIntelligenceGlobalSetting::defaultReplyTextPrompt()),
            'reply_text_fixed_templates_json' => $this->sanitizeTemplates($payload['reply_text_fixed_templates'] ?? []),
            'reply_prompt_preset_id' => $payload['reply_prompt_preset_id'] ?? null,
            'reply_ai_rate_limit_enabled' => (bool) ($payload['reply_ai_rate_limit_enabled'] ?? false),
            'reply_ai_rate_limit_max_messages' => max(1, (int) ($payload['reply_ai_rate_limit_max_messages'] ?? 10)),
            'reply_ai_rate_limit_window_minutes' => max(1, (int) ($payload['reply_ai_rate_limit_window_minutes'] ?? 10)),
        ]);

        $settings->save();

        return $settings->refresh();
    }

    /**
     * @param mixed $templates
     * @return array<int, string>
     */
    private function sanitizeTemplates(mixed $templates): array
    {
        if (! is_array($templates)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): ?string => is_string($item) && trim($item) !== '' ? trim($item) : null,
            $templates,
        )));
    }
}
