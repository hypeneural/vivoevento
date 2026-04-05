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
                'require_json_output' => (bool) ($payload['require_json_output'] ?? $defaults['require_json_output']),
            ],
        );
    }
}
