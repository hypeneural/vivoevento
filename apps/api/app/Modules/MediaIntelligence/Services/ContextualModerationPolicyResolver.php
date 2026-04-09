<?php

namespace App\Modules\MediaIntelligence\Services;

use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaIntelligence\Models\MediaIntelligenceGlobalSetting;

class ContextualModerationPolicyResolver
{
    public function __construct(
        private readonly ContextualModerationPresetCatalog $presets,
    ) {}

    public function resolveGlobal(): MediaIntelligenceGlobalSetting
    {
        return MediaIntelligenceGlobalSetting::query()->firstOrNew(
            ['id' => 1],
            MediaIntelligenceGlobalSetting::defaultAttributes(),
        );
    }

    /**
     * @param array<string, mixed> $runtimeOverrides
     * @return array{
     *   settings: EventMediaIntelligenceSetting,
     *   snapshot: array<string, mixed>,
     *   sources: array<string, string>
     * }
     */
    public function resolveForGlobal(array $runtimeOverrides = []): array
    {
        return $this->resolve(null, null, $runtimeOverrides);
    }

    /**
     * @param array<string, mixed> $runtimeOverrides
     * @return array{
     *   settings: EventMediaIntelligenceSetting,
     *   snapshot: array<string, mixed>,
     *   sources: array<string, string>
     * }
     */
    public function resolveForEvent(Event $event, array $runtimeOverrides = []): array
    {
        /** @var EventMediaIntelligenceSetting|null $eventSettings */
        $eventSettings = $event->relationLoaded('mediaIntelligenceSettings')
            ? $event->mediaIntelligenceSettings
            : $event->mediaIntelligenceSettings()->first();

        return $this->resolve($event, $eventSettings, $runtimeOverrides);
    }

    /**
     * @param array<string, mixed> $runtimeOverrides
     * @return array{
     *   settings: EventMediaIntelligenceSetting,
     *   snapshot: array<string, mixed>,
     *   sources: array<string, string>
     * }
     */
    private function resolve(
        ?Event $event,
        ?EventMediaIntelligenceSetting $eventSettings,
        array $runtimeOverrides = [],
    ): array {
        $global = $this->resolveGlobal();
        $defaults = EventMediaIntelligenceSetting::defaultAttributes();

        $resolvedPresetKey = $this->resolveField(
            'contextual_policy_preset_key',
            $defaults,
            $global,
            $eventSettings,
        );
        $preset = $this->presets->resolve((string) $resolvedPresetKey['value']);

        $snapshot = [];
        $sources = [];

        foreach ($this->baseFields() as $field) {
            $resolved = $this->resolveField($field, $defaults, $global, $eventSettings, $runtimeOverrides);
            $snapshot[$field] = $resolved['value'];
            $sources[$field] = $resolved['source'];
        }

        foreach ($this->policyFields() as $field) {
            $resolved = $this->resolvePolicyField($field, $defaults, $global, $eventSettings, $preset, $runtimeOverrides);
            $snapshot[$field] = $resolved['value'];
            $sources[$field] = $resolved['source'];
        }

        $snapshot['contextual_policy_preset_label'] = $preset['label'];
        $sources['contextual_policy_preset_label'] = 'preset';

        $settingsAttributes = array_merge(
            $snapshot,
            [
                'event_id' => $event?->id,
                'approval_prompt' => $eventSettings?->approval_prompt
                    ?? $defaults['approval_prompt'],
                'reply_text_enabled' => $eventSettings?->reply_text_enabled
                    ?? $defaults['reply_text_enabled'],
                'reply_text_mode' => $eventSettings?->reply_text_mode
                    ?? $defaults['reply_text_mode'],
                'reply_prompt_override' => $eventSettings?->reply_prompt_override
                    ?? $defaults['reply_prompt_override'],
                'reply_fixed_templates_json' => $eventSettings?->reply_fixed_templates_json
                    ?? $defaults['reply_fixed_templates_json'],
                'reply_prompt_preset_id' => $eventSettings?->reply_prompt_preset_id
                    ?? $defaults['reply_prompt_preset_id'],
            ],
        );

        $resolvedSettings = new EventMediaIntelligenceSetting($settingsAttributes);
        $resolvedSettings->setAttribute('id', $eventSettings?->id);
        $resolvedSettings->setAttribute('created_at', $eventSettings?->created_at);
        $resolvedSettings->setAttribute('updated_at', $eventSettings?->updated_at);
        $resolvedSettings->setAttribute('inherits_global', $eventSettings === null);

        return [
            'settings' => $resolvedSettings,
            'snapshot' => $snapshot,
            'sources' => $sources,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function baseFields(): array
    {
        return [
            'enabled',
            'provider_key',
            'model_key',
            'mode',
            'prompt_version',
            'response_schema_version',
            'timeout_ms',
            'fallback_mode',
            'context_scope',
            'reply_scope',
            'normalized_text_context_mode',
            'require_json_output',
            'caption_style_prompt',
            'contextual_policy_preset_key',
            'policy_version',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function policyFields(): array
    {
        return [
            'allow_alcohol',
            'allow_tobacco',
            'required_people_context',
            'blocked_terms_json',
            'allowed_exceptions_json',
            'freeform_instruction',
        ];
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $runtimeOverrides
     * @return array{value:mixed,source:string}
     */
    private function resolveField(
        string $field,
        array $defaults,
        MediaIntelligenceGlobalSetting $global,
        ?EventMediaIntelligenceSetting $eventSettings,
        array $runtimeOverrides = [],
    ): array {
        if (array_key_exists($field, $runtimeOverrides)) {
            return [
                'value' => $runtimeOverrides[$field],
                'source' => 'runtime_override',
            ];
        }

        $eventValue = $eventSettings?->getAttribute($field);

        if ($eventSettings && $eventValue !== null) {
            return [
                'value' => $eventValue,
                'source' => 'event_setting',
            ];
        }

        $globalValue = $global->getAttribute($field);

        if ($globalValue !== null) {
            return [
                'value' => $globalValue,
                'source' => 'global_setting',
            ];
        }

        return [
            'value' => $defaults[$field] ?? null,
            'source' => 'default_config',
        ];
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $preset
     * @param array<string, mixed> $runtimeOverrides
     * @return array{value:mixed,source:string}
     */
    private function resolvePolicyField(
        string $field,
        array $defaults,
        MediaIntelligenceGlobalSetting $global,
        ?EventMediaIntelligenceSetting $eventSettings,
        array $preset,
        array $runtimeOverrides = [],
    ): array {
        if (array_key_exists($field, $runtimeOverrides)) {
            return [
                'value' => $runtimeOverrides[$field],
                'source' => 'runtime_override',
            ];
        }

        $eventValue = $eventSettings?->getAttribute($field);

        if ($eventSettings && $eventValue !== null) {
            return [
                'value' => $eventValue,
                'source' => 'event_setting',
            ];
        }

        $globalValue = $global->getAttribute($field);

        if ($globalValue !== null) {
            return [
                'value' => $globalValue,
                'source' => 'global_setting',
            ];
        }

        if (array_key_exists($field, $preset)) {
            return [
                'value' => $preset[$field],
                'source' => 'preset',
            ];
        }

        return [
            'value' => $defaults[$field] ?? null,
            'source' => 'default_config',
        ];
    }
}
