<?php

namespace App\Modules\MediaIntelligence\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaIntelligence\Services\ContextualModerationPolicyResolver;

class UpsertEventMediaIntelligenceSettingsAction
{
    public function __construct(
        private readonly ContextualModerationPolicyResolver $resolver,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(Event $event, array $payload): EventMediaIntelligenceSetting
    {
        if ((bool) ($payload['inherit_global'] ?? false)) {
            $event->mediaIntelligenceSettings()->delete();

            return $this->resolver->resolveForEvent($event->fresh())['settings'];
        }

        $defaults = EventMediaIntelligenceSetting::defaultAttributes();
        $replyTextMode = EventMediaIntelligenceSetting::normalizeReplyTextMode(
            isset($payload['reply_text_mode']) ? (string) $payload['reply_text_mode'] : null,
            array_key_exists('reply_text_enabled', $payload) ? (bool) $payload['reply_text_enabled'] : null,
        );
        $resolvedCurrent = $this->resolver->resolveForEvent($event);
        /** @var EventMediaIntelligenceSetting $current */
        $current = $resolvedCurrent['settings'];

        $freeformInstruction = $payload['freeform_instruction'] ?? $payload['approval_prompt'] ?? $current->contextualFreeformInstruction();

        $settings = EventMediaIntelligenceSetting::query()->updateOrCreate(
            [
                'event_id' => $event->id,
            ],
            [
                'provider_key' => (string) ($payload['provider_key'] ?? $current->provider_key ?? $defaults['provider_key']),
                'model_key' => (string) ($payload['model_key'] ?? $current->model_key ?? $defaults['model_key']),
                'enabled' => (bool) ($payload['enabled'] ?? $current->enabled ?? $defaults['enabled']),
                'mode' => (string) ($payload['mode'] ?? $current->mode ?? $defaults['mode']),
                'prompt_version' => (string) ($payload['prompt_version'] ?? $current->prompt_version ?? $defaults['prompt_version']),
                'approval_prompt' => is_string($freeformInstruction) ? trim($freeformInstruction) : null,
                'freeform_instruction' => is_string($freeformInstruction) && trim($freeformInstruction) !== '' ? trim($freeformInstruction) : null,
                'caption_style_prompt' => (string) ($payload['caption_style_prompt'] ?? $current->caption_style_prompt ?? $defaults['caption_style_prompt']),
                'response_schema_version' => (string) ($payload['response_schema_version'] ?? $current->response_schema_version ?? $defaults['response_schema_version']),
                'timeout_ms' => (int) ($payload['timeout_ms'] ?? $current->timeout_ms ?? $defaults['timeout_ms']),
                'fallback_mode' => (string) ($payload['fallback_mode'] ?? $current->fallback_mode ?? $defaults['fallback_mode']),
                'context_scope' => (string) ($payload['context_scope'] ?? $current->context_scope ?? $defaults['context_scope']),
                'reply_scope' => (string) ($payload['reply_scope'] ?? $current->reply_scope ?? $defaults['reply_scope']),
                'normalized_text_context_mode' => (string) ($payload['normalized_text_context_mode'] ?? $current->normalized_text_context_mode ?? $defaults['normalized_text_context_mode']),
                'contextual_policy_preset_key' => (string) ($payload['contextual_policy_preset_key'] ?? $current->contextual_policy_preset_key ?? $defaults['contextual_policy_preset_key']),
                'policy_version' => (string) ($payload['policy_version'] ?? $current->policy_version ?? $defaults['policy_version']),
                'allow_alcohol' => (bool) ($payload['allow_alcohol'] ?? $current->allow_alcohol ?? $defaults['allow_alcohol']),
                'allow_tobacco' => (bool) ($payload['allow_tobacco'] ?? $current->allow_tobacco ?? $defaults['allow_tobacco']),
                'required_people_context' => (string) ($payload['required_people_context'] ?? $current->required_people_context ?? $defaults['required_people_context']),
                'blocked_terms_json' => $this->sanitizeTemplates($payload['blocked_terms'] ?? $current->blocked_terms_json ?? $defaults['blocked_terms_json']),
                'allowed_exceptions_json' => $this->sanitizeTemplates($payload['allowed_exceptions'] ?? $current->allowed_exceptions_json ?? $defaults['allowed_exceptions_json']),
                'require_json_output' => (bool) ($payload['require_json_output'] ?? $current->require_json_output ?? $defaults['require_json_output']),
                'reply_text_enabled' => $replyTextMode !== 'disabled',
                'reply_text_mode' => $replyTextMode,
                'reply_prompt_override' => $payload['reply_prompt_override'] ?? $current->reply_prompt_override ?? $defaults['reply_prompt_override'],
                'reply_fixed_templates_json' => $this->sanitizeTemplates($payload['reply_fixed_templates'] ?? $current->reply_fixed_templates_json ?? $defaults['reply_fixed_templates_json']),
                'reply_prompt_preset_id' => $payload['reply_prompt_preset_id'] ?? $current->reply_prompt_preset_id ?? $defaults['reply_prompt_preset_id'],
            ],
        );

        return $this->resolver->resolveForEvent($event->fresh())['settings'];
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
