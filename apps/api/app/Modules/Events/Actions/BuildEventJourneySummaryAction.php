<?php

namespace App\Modules\Events\Actions;

use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;

class BuildEventJourneySummaryAction
{
    /**
     * @param array<string, mixed> $intakeChannels
     * @param array<string, bool> $destinations
     * @return array{human_text: string}
     */
    public function execute(
        Event $event,
        array $intakeChannels,
        EventContentModerationSetting $contentModeration,
        EventMediaIntelligenceSetting $mediaIntelligence,
        array $destinations = [],
    ): array {
        $destinations = array_replace([
            'gallery' => true,
            'wall' => false,
            'print' => false,
        ], $destinations);

        $activeChannels = $this->activeChannelLabels($intakeChannels);

        if ($activeChannels === []) {
            return [
                'human_text' => 'Sem canais de recebimento ativos, a jornada fica pronta para configuracao e nenhuma midia nova entra no fluxo.',
            ];
        }

        $entry = sprintf(
            'Quando a midia chega por %s, o Evento Vivo',
            $this->humanList($activeChannels),
        );

        $decision = $this->decisionClause($event, $contentModeration, $mediaIntelligence);
        $reply = $this->replyClause($intakeChannels, $mediaIntelligence);
        $output = $this->outputClause($destinations);

        return [
            'human_text' => "{$entry} {$decision}, {$reply} e {$output}.",
        ];
    }

    /**
     * @param array<string, mixed> $intakeChannels
     * @return array<int, string>
     */
    private function activeChannelLabels(array $intakeChannels): array
    {
        return array_values(array_filter([
            (bool) data_get($intakeChannels, 'whatsapp_direct.enabled', false) ? 'WhatsApp privado' : null,
            (bool) data_get($intakeChannels, 'whatsapp_groups.enabled', false) ? 'grupos de WhatsApp' : null,
            (bool) data_get($intakeChannels, 'telegram.enabled', false) ? 'Telegram' : null,
            (bool) data_get($intakeChannels, 'public_upload.enabled', false) ? 'link de envio' : null,
        ]));
    }

    /**
     * @param array<int, string> $items
     */
    private function humanList(array $items): string
    {
        $items = array_values($items);
        $count = count($items);

        if ($count === 1) {
            return $items[0];
        }

        if ($count === 2) {
            return "{$items[0]} e {$items[1]}";
        }

        $last = array_pop($items);

        return implode(', ', $items) . " e {$last}";
    }

    private function decisionClause(
        Event $event,
        EventContentModerationSetting $contentModeration,
        EventMediaIntelligenceSetting $mediaIntelligence,
    ): string {
        $moderationMode = $event->moderation_mode?->value ?? 'manual';

        if ($moderationMode === 'none') {
            return 'aprova automaticamente';
        }

        if ($moderationMode === 'manual') {
            return 'envia para revisao manual';
        }

        $safetyEnabled = (bool) $contentModeration->enabled;
        $mediaIntelligenceEnabled = (bool) $mediaIntelligence->enabled;

        if ($safetyEnabled && $contentModeration->mode === 'enforced' && $mediaIntelligenceEnabled && $mediaIntelligence->mode === 'gate') {
            return 'analisa risco e contexto com IA antes de publicar';
        }

        if ($safetyEnabled && $contentModeration->mode === 'enforced') {
            return 'analisa risco com IA antes de publicar';
        }

        if ($safetyEnabled && $contentModeration->mode === 'observe_only' && $mediaIntelligenceEnabled) {
            return 'usa IA para entender melhor a midia e sinalizar revisao quando necessario';
        }

        if ($safetyEnabled && $contentModeration->mode === 'observe_only') {
            return 'usa IA para sinalizar revisao quando necessario';
        }

        if ($mediaIntelligenceEnabled && $mediaIntelligence->mode === 'gate') {
            return 'analisa o contexto com IA antes de publicar';
        }

        if ($mediaIntelligenceEnabled) {
            return 'usa IA para entender melhor a midia antes da decisao';
        }

        return 'segue em modo IA, mas ainda sem analises ativas';
    }

    /**
     * @param array<string, mixed> $intakeChannels
     */
    private function replyClause(array $intakeChannels, EventMediaIntelligenceSetting $mediaIntelligence): string
    {
        $replyCapableChannelActive = (bool) data_get($intakeChannels, 'whatsapp_direct.enabled', false)
            || (bool) data_get($intakeChannels, 'whatsapp_groups.enabled', false)
            || (bool) data_get($intakeChannels, 'telegram.enabled', false);

        if (! $replyCapableChannelActive || ! $mediaIntelligence->automaticReplyEnabled()) {
            return 'nao envia resposta automatica';
        }

        return match ($mediaIntelligence->resolvedReplyTextMode()) {
            'ai' => 'responde automaticamente com IA',
            'fixed_random' => 'responde com mensagem pronta',
            default => 'nao envia resposta automatica',
        };
    }

    /**
     * @param array<string, bool> $destinations
     */
    private function outputClause(array $destinations): string
    {
        $gallery = (bool) ($destinations['gallery'] ?? false);
        $wall = (bool) ($destinations['wall'] ?? false);

        if ($gallery && $wall) {
            return 'publica na galeria e no telao';
        }

        if ($gallery) {
            return 'publica na galeria';
        }

        if ($wall) {
            return 'envia para o telao';
        }

        return 'nao publica em destinos visiveis';
    }
}
