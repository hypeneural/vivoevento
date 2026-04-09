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
        $defaults = MediaIntelligenceGlobalSetting::defaultAttributes();
        $settings = MediaIntelligenceGlobalSetting::query()->firstOrNew(
            ['id' => 1],
            $defaults,
        );

        $settings->fill([
            'enabled' => (bool) ($payload['enabled'] ?? $settings->enabled ?? $defaults['enabled']),
            'provider_key' => (string) ($payload['provider_key'] ?? $settings->provider_key ?? $defaults['provider_key']),
            'model_key' => (string) ($payload['model_key'] ?? $settings->model_key ?? $defaults['model_key']),
            'mode' => (string) ($payload['mode'] ?? $settings->mode ?? $defaults['mode']),
            'prompt_version' => (string) ($payload['prompt_version'] ?? $settings->prompt_version ?? $defaults['prompt_version']),
            'response_schema_version' => (string) ($payload['response_schema_version'] ?? $settings->response_schema_version ?? $defaults['response_schema_version']),
            'timeout_ms' => (int) ($payload['timeout_ms'] ?? $settings->timeout_ms ?? $defaults['timeout_ms']),
            'fallback_mode' => (string) ($payload['fallback_mode'] ?? $settings->fallback_mode ?? $defaults['fallback_mode']),
            'context_scope' => (string) ($payload['context_scope'] ?? $settings->context_scope ?? $defaults['context_scope']),
            'reply_scope' => (string) ($payload['reply_scope'] ?? $settings->reply_scope ?? $defaults['reply_scope']),
            'normalized_text_context_mode' => (string) ($payload['normalized_text_context_mode'] ?? $settings->normalized_text_context_mode ?? $defaults['normalized_text_context_mode']),
            'require_json_output' => (bool) ($payload['require_json_output'] ?? $settings->require_json_output ?? $defaults['require_json_output']),
            'contextual_policy_preset_key' => (string) ($payload['contextual_policy_preset_key'] ?? $settings->contextual_policy_preset_key ?? $defaults['contextual_policy_preset_key']),
            'policy_version' => (string) ($payload['policy_version'] ?? $settings->policy_version ?? $defaults['policy_version']),
            'allow_alcohol' => (bool) ($payload['allow_alcohol'] ?? $settings->allow_alcohol ?? $defaults['allow_alcohol']),
            'allow_tobacco' => (bool) ($payload['allow_tobacco'] ?? $settings->allow_tobacco ?? $defaults['allow_tobacco']),
            'required_people_context' => (string) ($payload['required_people_context'] ?? $settings->required_people_context ?? $defaults['required_people_context']),
            'blocked_terms_json' => $this->sanitizeTemplates($payload['blocked_terms'] ?? $settings->blocked_terms_json ?? $defaults['blocked_terms_json']),
            'allowed_exceptions_json' => $this->sanitizeTemplates($payload['allowed_exceptions'] ?? $settings->allowed_exceptions_json ?? $defaults['allowed_exceptions_json']),
            'freeform_instruction' => $this->nullableTrimmedString($payload['freeform_instruction'] ?? $settings->freeform_instruction ?? $defaults['freeform_instruction']),
            'reply_text_prompt' => (string) ($payload['reply_text_prompt'] ?? $settings->reply_text_prompt ?? MediaIntelligenceGlobalSetting::defaultReplyTextPrompt()),
            'reply_text_fixed_templates_json' => $this->sanitizeTemplates($payload['reply_text_fixed_templates'] ?? $settings->reply_text_fixed_templates_json ?? []),
            'reply_prompt_preset_id' => $payload['reply_prompt_preset_id'] ?? null,
            'reply_ai_rate_limit_enabled' => (bool) ($payload['reply_ai_rate_limit_enabled'] ?? $settings->reply_ai_rate_limit_enabled ?? false),
            'reply_ai_rate_limit_max_messages' => max(1, (int) ($payload['reply_ai_rate_limit_max_messages'] ?? $settings->reply_ai_rate_limit_max_messages ?? 10)),
            'reply_ai_rate_limit_window_minutes' => max(1, (int) ($payload['reply_ai_rate_limit_window_minutes'] ?? $settings->reply_ai_rate_limit_window_minutes ?? 10)),
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

    private function nullableTrimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
