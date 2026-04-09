<?php

namespace App\Modules\MediaIntelligence\Services;

use App\Modules\ContentModeration\Models\EventMediaSafetyEvaluation;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaEffectiveStateResolver;
use App\Modules\MediaProcessing\Models\MediaProcessingRun;
use App\Modules\MediaIntelligence\Models\EventMediaVlmEvaluation;

class MediaOperationalHistorySummaryService
{
    public function __construct(
        private readonly MediaEffectiveStateResolver $stateResolver,
        private readonly ContextualModerationPresetCatalog $presetCatalog,
    ) {}

    /**
     * @return array{
     *     effective_media_state: string,
     *     safety_decision: string,
     *     safety_is_blocking: bool,
     *     context_decision: string,
     *     context_is_blocking: bool,
     *     operator_decision: string,
     *     publication_decision: string,
     *     human_reason: string,
     *     policy_label: string|null,
     *     policy_inheritance_mode: string,
     *     text_context_summary: string
     * }
     */
    public function summarize(EventMedia $media): array
    {
        $media->loadMissing([
            'latestSafetyEvaluation',
            'latestVlmEvaluation',
            'latestVlmRun',
            'event.contentModerationSettings',
            'event.mediaIntelligenceSettings',
        ]);

        $state = $this->stateResolver->resolve($media);
        $evaluation = $media->latestVlmEvaluation;
        $promptContext = $evaluation?->prompt_context_json ?? [];

        return [
            ...$state,
            'human_reason' => $this->humanReason(
                $media,
                $state,
                $evaluation,
                $media->latestSafetyEvaluation,
                $media->latestVlmRun,
            ),
            'policy_label' => $this->policyLabel($promptContext, $evaluation?->policy_snapshot_json ?? []),
            'policy_inheritance_mode' => $this->policyInheritanceMode($evaluation?->policy_sources_json ?? []),
            'text_context_summary' => $this->textContextSummary(
                $evaluation?->input_scope_used,
                $evaluation?->normalized_text_context ?? ($promptContext['normalized_text_context'] ?? null),
            ),
        ];
    }

    /**
     * @param  array<string, bool|string|null>  $state
     */
    private function humanReason(
        EventMedia $media,
        array $state,
        ?EventMediaVlmEvaluation $evaluation,
        ?EventMediaSafetyEvaluation $safetyEvaluation,
        ?MediaProcessingRun $run,
    ): string {
        $reason = trim((string) ($evaluation?->reason ?? ''));

        if ($reason !== '') {
            return $reason;
        }

        if (($state['operator_decision'] ?? null) === 'rejected') {
            return 'Rejeitada manualmente por operador.';
        }

        if (($state['context_decision'] ?? null) === 'rejected') {
            return 'Bloqueada pelo gate contextual do evento.';
        }

        if (($state['safety_decision'] ?? null) === 'rejected') {
            $reasonCodes = $safetyEvaluation?->reason_codes_json ?? [];

            if ($reasonCodes !== []) {
                return 'Bloqueada pela Safety objetiva: ' . implode(', ', $reasonCodes) . '.';
            }

            return 'Bloqueada pela Safety objetiva.';
        }

        $errorMessage = trim((string) ($run?->error_message ?? $media->last_pipeline_error_message ?? ''));

        return match ($state['effective_media_state'] ?? null) {
            'published' => 'Midia publicada para o evento.',
            'approved' => 'Midia aprovada e aguardando publicacao.',
            'pending_moderation' => 'Midia aguardando conclusao das etapas de moderacao.',
            'hidden' => 'Midia ocultada da publicacao.',
            'processing' => 'Midia ainda em processamento.',
            'error' => $errorMessage !== '' ? $errorMessage : 'Falha no pipeline de moderacao.',
            default => $errorMessage !== '' ? $errorMessage : 'Sem explicacao operacional disponivel.',
        };
    }

    /**
     * @param  array<string, mixed>  $promptContext
     * @param  array<string, mixed>  $policySnapshot
     */
    private function policyLabel(array $promptContext, array $policySnapshot): ?string
    {
        $presetName = trim((string) ($promptContext['preset_name'] ?? ''));

        if ($presetName !== '') {
            return $presetName;
        }

        $presetKey = trim((string) ($policySnapshot['contextual_policy_preset_key'] ?? ''));

        if ($presetKey === '') {
            return null;
        }

        return $this->presetCatalog->resolve($presetKey)['label'] ?? $presetKey;
    }

    /**
     * @param  array<string, mixed>  $policySources
     */
    private function policyInheritanceMode(array $policySources): string
    {
        $values = array_values(array_filter(array_map(
            static fn ($value): ?string => is_string($value) ? $value : null,
            $policySources,
        )));

        if (in_array('runtime_fallback', $values, true)) {
            return 'runtime_fallback';
        }

        if (in_array('event_setting', $values, true)) {
            return 'event_override';
        }

        if (in_array('global_setting', $values, true)) {
            return 'global';
        }

        if (in_array('preset', $values, true)) {
            return 'preset';
        }

        return 'unknown';
    }

    private function textContextSummary(?string $inputScopeUsed, ?string $normalizedTextContext): string
    {
        return match ($inputScopeUsed) {
            'image_only' => 'A decisao usou somente a imagem.',
            'image_and_text_context' => trim((string) $normalizedTextContext) !== ''
                ? 'A decisao usou imagem + texto normalizado.'
                : 'A decisao usou imagem + texto recebido.',
            default => 'Escopo textual nao informado.',
        };
    }
}
