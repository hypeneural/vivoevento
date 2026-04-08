<?php

namespace App\Modules\MediaIntelligence\Services;

use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaIntelligence\Models\MediaReplyPromptPreset;
use App\Modules\MediaIntelligence\Models\MediaIntelligenceGlobalSetting;

class MediaReplyTextPromptResolver
{
    public const EVENT_NAME_VARIABLE = '{nome_do_evento}';

    public function enabled(?EventMediaIntelligenceSetting $settings): bool
    {
        return $settings?->usesAiAutomaticReply() ?? false;
    }

    public function effectivePrompt(?EventMediaIntelligenceSetting $settings): ?string
    {
        return $this->promptContext($settings)['resolved'] ?? null;
    }

    /**
     * @return array{template:string, variables:array<string, string>, resolved:string}|null
     */
    public function promptContext(?EventMediaIntelligenceSetting $settings, ?string $eventName = null): ?array
    {
        if (! $this->enabled($settings)) {
            return null;
        }

        $global = MediaIntelligenceGlobalSetting::query()->firstOrNew(
            ['id' => 1],
            MediaIntelligenceGlobalSetting::defaultAttributes(),
        );

        $instructionTemplate = trim((string) ($settings?->reply_prompt_override ?? ''));
        $instructionSource = 'event';

        if ($instructionTemplate === '') {
            $instructionTemplate = trim((string) ($global->reply_text_prompt ?? ''));
            $instructionSource = 'global';
        }

        if ($instructionTemplate === '') {
            $instructionTemplate = MediaIntelligenceGlobalSetting::defaultReplyTextPrompt();
            $instructionSource = 'default';
        }

        $preset = $this->resolvePreset(
            $settings?->reply_prompt_preset_id,
            $global->reply_prompt_preset_id,
        );

        return $this->composePromptContext(
            eventName: $eventName,
            instructionTemplate: $instructionTemplate,
            instructionSource: $instructionSource,
            preset: $preset['model'],
            presetSource: $preset['source'],
        );
    }

    /**
     * @return array{template:string, variables:array<string, string>, resolved:string, preset_id:int|null, preset_name:string|null, preset_source:string|null, instruction_source:string}|null
     */
    public function composePromptContext(
        ?string $eventName,
        ?string $instructionTemplate,
        string $instructionSource = 'manual',
        ?MediaReplyPromptPreset $preset = null,
        ?string $presetSource = null,
    ): ?array {
        $segments = [];

        if ($preset && $preset->is_active) {
            $presetTemplate = trim((string) $preset->prompt_template);

            if ($presetTemplate !== '') {
                $segments[] = $presetTemplate;
            }
        }

        $instructionTemplate = trim((string) $instructionTemplate);

        if ($instructionTemplate !== '') {
            $segments[] = $instructionTemplate;
        }

        $template = $segments !== []
            ? implode("\n\n", array_values(array_unique($segments)))
            : MediaIntelligenceGlobalSetting::defaultReplyTextPrompt();

        $variables = [
            'nome_do_evento' => trim((string) $eventName),
        ];

        $resolved = str_replace(
            self::EVENT_NAME_VARIABLE,
            $variables['nome_do_evento'],
            $template,
        );

        $resolved = preg_replace('/\s+/u', ' ', trim($resolved)) ?: '';

        return [
            'template' => $template,
            'variables' => $variables,
            'resolved' => $resolved,
            'preset_id' => $preset?->id,
            'preset_name' => $preset?->name,
            'preset_source' => $preset && $preset->is_active ? $presetSource : null,
            'instruction_source' => $instructionSource,
        ];
    }

    /**
     * @return array{model:MediaReplyPromptPreset|null,source:string|null}
     */
    private function resolvePreset(?int $eventPresetId, ?int $globalPresetId): array
    {
        foreach ([
            ['id' => $eventPresetId, 'source' => 'event'],
            ['id' => $globalPresetId, 'source' => 'global'],
        ] as $candidate) {
            $presetId = $candidate['id'];

            if (! is_int($presetId) || $presetId <= 0) {
                continue;
            }

            $preset = MediaReplyPromptPreset::query()->find($presetId);

            if ($preset && $preset->is_active) {
                return [
                    'model' => $preset,
                    'source' => $candidate['source'],
                ];
            }
        }

        return [
            'model' => null,
            'source' => null,
        ];
    }
}
