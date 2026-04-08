<?php

namespace App\Modules\MediaIntelligence\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;

class UpsertEventMediaIntelligenceSettingsAction
{
    /**
     * @param array<string, mixed> $payload
     */
    public function execute(Event $event, array $payload): EventMediaIntelligenceSetting
    {
        $defaults = EventMediaIntelligenceSetting::defaultAttributes();
        $replyTextMode = EventMediaIntelligenceSetting::normalizeReplyTextMode(
            isset($payload['reply_text_mode']) ? (string) $payload['reply_text_mode'] : null,
            array_key_exists('reply_text_enabled', $payload) ? (bool) $payload['reply_text_enabled'] : null,
        );

        return EventMediaIntelligenceSetting::query()->updateOrCreate(
            [
                'event_id' => $event->id,
            ],
            [
                'provider_key' => (string) ($payload['provider_key'] ?? $defaults['provider_key']),
                'model_key' => (string) ($payload['model_key'] ?? $defaults['model_key']),
                'enabled' => (bool) ($payload['enabled'] ?? $defaults['enabled']),
                'mode' => (string) ($payload['mode'] ?? $defaults['mode']),
                'prompt_version' => (string) ($payload['prompt_version'] ?? $defaults['prompt_version']),
                'approval_prompt' => (string) ($payload['approval_prompt'] ?? $defaults['approval_prompt']),
                'caption_style_prompt' => (string) ($payload['caption_style_prompt'] ?? $defaults['caption_style_prompt']),
                'response_schema_version' => (string) ($payload['response_schema_version'] ?? $defaults['response_schema_version']),
                'timeout_ms' => (int) ($payload['timeout_ms'] ?? $defaults['timeout_ms']),
                'fallback_mode' => (string) ($payload['fallback_mode'] ?? $defaults['fallback_mode']),
                'context_scope' => (string) ($payload['context_scope'] ?? $defaults['context_scope']),
                'reply_scope' => (string) ($payload['reply_scope'] ?? $defaults['reply_scope']),
                'normalized_text_context_mode' => (string) ($payload['normalized_text_context_mode'] ?? $defaults['normalized_text_context_mode']),
                'require_json_output' => (bool) ($payload['require_json_output'] ?? $defaults['require_json_output']),
                'reply_text_enabled' => $replyTextMode !== 'disabled',
                'reply_text_mode' => $replyTextMode,
                'reply_prompt_override' => $payload['reply_prompt_override'] ?? $defaults['reply_prompt_override'],
                'reply_fixed_templates_json' => $this->sanitizeTemplates($payload['reply_fixed_templates'] ?? $defaults['reply_fixed_templates_json']),
                'reply_prompt_preset_id' => $payload['reply_prompt_preset_id'] ?? $defaults['reply_prompt_preset_id'],
            ],
        );
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
