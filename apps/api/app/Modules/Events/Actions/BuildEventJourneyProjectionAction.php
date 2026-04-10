<?php

namespace App\Modules\Events\Actions;

use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\ContentModeration\Services\ContentModerationSettingsResolver;
use App\Modules\Events\Data\EventJourneyBranchData;
use App\Modules\Events\Data\EventJourneyCapabilityData;
use App\Modules\Events\Data\EventJourneyNodeData;
use App\Modules\Events\Data\EventJourneyProjectionData;
use App\Modules\Events\Data\EventJourneyScenarioData;
use App\Modules\Events\Data\EventJourneyStageData;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Support\EventIntakeChannelsStateBuilder;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaIntelligence\Services\ContextualModerationPolicyResolver;

class BuildEventJourneyProjectionAction
{
    public function __construct(
        private readonly EventIntakeChannelsStateBuilder $intakeChannels,
        private readonly ContentModerationSettingsResolver $contentModerationSettings,
        private readonly ContextualModerationPolicyResolver $mediaIntelligenceSettings,
        private readonly BuildEventJourneySummaryAction $summary,
    ) {}

    public function execute(Event $event): EventJourneyProjectionData
    {
        $event->loadMissing([
            'modules',
            'channels',
            'defaultWhatsAppInstance',
            'whatsappGroupBindings',
            'mediaSenderBlacklists',
            'contentModerationSettings',
            'mediaIntelligenceSettings',
            'wallSettings',
        ]);

        $intakeState = $this->intakeChannels->build($event);
        $intakeDefaults = $intakeState['intake_defaults'];
        $intakeChannels = $intakeState['intake_channels'];
        $entitlements = (array) ($event->current_entitlements_json ?? []);
        $modules = $this->moduleStates($event);
        $contentModeration = $this->contentModerationSettings->resolveForEvent($event);
        $mediaIntelligence = $this->mediaIntelligenceSettings->resolveForEvent($event)['settings'];

        $warnings = $this->buildWarnings($event, $intakeChannels, $entitlements);
        $replyCapableChannelActive = $this->anyReplyCapableChannelActive($intakeChannels);
        $wallAvailable = $this->entitlementBoolean($entitlements, 'modules.wall', true);
        $wallEnabled = $modules['wall']
            && $wallAvailable
            && (bool) ($event->wallSettings?->is_enabled ?? false);

        $settings = [
            'moderation_mode' => $event->moderation_mode?->value,
            'modules' => $modules,
            'content_moderation' => $this->contentModerationPreview($contentModeration),
            'media_intelligence' => $this->mediaIntelligencePreview($mediaIntelligence),
            'destinations' => [
                'gallery' => true,
                'wall' => $wallEnabled,
                'print' => false,
            ],
        ];

        return new EventJourneyProjectionData(
            version: 'journey-builder-v1',
            event: [
                'id' => $event->id,
                'uuid' => $event->uuid,
                'title' => $event->title,
                'status' => $event->status?->value,
                'moderation_mode' => $event->moderation_mode?->value,
                'modules' => $modules,
            ],
            intake_defaults: $intakeDefaults,
            intake_channels: $intakeChannels,
            settings: $settings,
            capabilities: $this->buildCapabilities(
                event: $event,
                entitlements: $entitlements,
                modules: $modules,
                mediaIntelligence: $mediaIntelligence,
                wallEnabled: $wallEnabled,
                wallAvailable: $wallAvailable,
            ),
            stages: [
                new EventJourneyStageData(
                    id: 'entry',
                    label: 'Entrada',
                    description: 'Como fotos e videos chegam ao evento.',
                    position: 0,
                    nodes: $this->entryNodes($event, $intakeDefaults, $intakeChannels, $entitlements),
                ),
                new EventJourneyStageData(
                    id: 'processing',
                    label: 'Processamento',
                    description: 'Como a midia e salva, preparada e analisada.',
                    position: 320,
                    nodes: $this->processingNodes($event, $intakeChannels, $contentModeration, $mediaIntelligence),
                ),
                new EventJourneyStageData(
                    id: 'decision',
                    label: 'Decisao',
                    description: 'Como o evento decide aprovar, revisar ou bloquear.',
                    position: 640,
                    nodes: $this->decisionNodes($event, $contentModeration, $mediaIntelligence),
                ),
                new EventJourneyStageData(
                    id: 'output',
                    label: 'Saida',
                    description: 'O que acontece apos a decisao da midia.',
                    position: 960,
                    nodes: $this->outputNodes(
                        modules: $modules,
                        mediaIntelligence: $mediaIntelligence,
                        replyCapableChannelActive: $replyCapableChannelActive,
                        wallEnabled: $wallEnabled,
                        wallAvailable: $wallAvailable,
                    ),
                ),
            ],
            warnings: $warnings,
            simulation_presets: $this->simulationPresets(),
            summary: $this->summary->execute(
                event: $event,
                intakeChannels: $intakeChannels,
                contentModeration: $contentModeration,
                mediaIntelligence: $mediaIntelligence,
                destinations: [
                    'gallery' => true,
                    'wall' => $wallEnabled,
                    'print' => false,
                ],
            ),
        );
    }

    /**
     * @return array<int, EventJourneyNodeData>
     */
    private function entryNodes(Event $event, array $intakeDefaults, array $intakeChannels, array $entitlements): array
    {
        $directAvailable = $this->entitlementBoolean($entitlements, 'channels.whatsapp_direct.enabled', true);
        $groupsAvailable = $this->entitlementBoolean($entitlements, 'channels.whatsapp_groups.enabled', true);
        $telegramAvailable = $this->entitlementBoolean($entitlements, 'channels.telegram.enabled', true);
        $uploadAvailable = $this->entitlementBoolean($entitlements, 'channels.public_upload.enabled', true);
        $blacklistAvailable = $this->entitlementBoolean($entitlements, 'channels.blacklist.enabled', true);

        $directEnabled = (bool) data_get($intakeChannels, 'whatsapp_direct.enabled', false);
        $groupsEnabled = (bool) data_get($intakeChannels, 'whatsapp_groups.enabled', false);
        $telegramEnabled = (bool) data_get($intakeChannels, 'telegram.enabled', false);
        $uploadEnabled = (bool) data_get($intakeChannels, 'public_upload.enabled', false);
        $activeBlacklistCount = $event->mediaSenderBlacklists
            ->filter(fn ($entry): bool => $entry->isCurrentlyActive())
            ->count();

        return [
            $this->node(
                id: 'entry_whatsapp_direct',
                stage: 'entry',
                kind: 'entry',
                label: 'WhatsApp privado',
                description: 'Recebe fotos e videos enviados por conversa privada.',
                active: $directEnabled && $directAvailable,
                editable: true,
                status: $this->status($directEnabled, $directAvailable),
                summary: $directEnabled ? 'Recebe midias por codigo privado.' : 'WhatsApp privado desligado.',
                configPreview: [
                    'enabled' => $directEnabled,
                    'available' => $directAvailable,
                    'media_inbox_code' => data_get($intakeChannels, 'whatsapp_direct.media_inbox_code'),
                    'session_ttl_minutes' => data_get($intakeChannels, 'whatsapp_direct.session_ttl_minutes'),
                    'whatsapp_instance_id' => data_get($intakeDefaults, 'whatsapp_instance_id'),
                    'whatsapp_instance_mode' => data_get($intakeDefaults, 'whatsapp_instance_mode', 'shared'),
                ],
                branches: [$this->branch('default', 'Padrao', 'processing_receive_feedback')],
            ),
            $this->node(
                id: 'entry_whatsapp_groups',
                stage: 'entry',
                kind: 'entry',
                label: 'WhatsApp grupos',
                description: 'Recebe midias enviadas em grupos vinculados ao evento.',
                active: $groupsEnabled && $groupsAvailable,
                editable: true,
                status: $this->status($groupsEnabled, $groupsAvailable),
                summary: $groupsEnabled
                    ? sprintf('Recebe midias de %d grupo(s).', count((array) data_get($intakeChannels, 'whatsapp_groups.groups', [])))
                    : 'WhatsApp grupos desligado.',
                configPreview: [
                    'enabled' => $groupsEnabled,
                    'available' => $groupsAvailable,
                    'group_count' => count((array) data_get($intakeChannels, 'whatsapp_groups.groups', [])),
                    'max_groups' => data_get($entitlements, 'channels.whatsapp_groups.max'),
                ],
                branches: [$this->branch('default', 'Padrao', 'processing_receive_feedback')],
            ),
            $this->node(
                id: 'entry_telegram',
                stage: 'entry',
                kind: 'entry',
                label: 'Telegram',
                description: 'Recebe midias enviadas ao bot do Telegram.',
                active: $telegramEnabled && $telegramAvailable,
                editable: true,
                status: $this->status($telegramEnabled, $telegramAvailable),
                summary: $telegramEnabled ? 'Recebe midias pelo bot do Telegram.' : 'Telegram desligado.',
                configPreview: [
                    'enabled' => $telegramEnabled,
                    'available' => $telegramAvailable,
                    'bot_username' => data_get($intakeChannels, 'telegram.bot_username'),
                    'media_inbox_code' => data_get($intakeChannels, 'telegram.media_inbox_code'),
                    'session_ttl_minutes' => data_get($intakeChannels, 'telegram.session_ttl_minutes'),
                ],
                branches: [$this->branch('default', 'Padrao', 'processing_receive_feedback')],
            ),
            $this->node(
                id: 'entry_public_upload',
                stage: 'entry',
                kind: 'entry',
                label: 'Link de envio',
                description: 'Recebe midias pelo link publico ou QR code do evento.',
                active: $uploadEnabled && $uploadAvailable,
                editable: true,
                status: $this->status($uploadEnabled, $uploadAvailable),
                summary: $uploadEnabled ? 'Recebe midias pelo link publico do evento.' : 'Link de envio desligado.',
                configPreview: [
                    'enabled' => $uploadEnabled,
                    'available' => $uploadAvailable,
                    'upload_url' => $event->publicUploadUrl(),
                ],
                branches: [$this->branch('default', 'Padrao', 'processing_receive_feedback')],
            ),
            $this->node(
                id: 'entry_sender_blacklist',
                stage: 'entry',
                kind: 'policy',
                label: 'Bloqueio de remetentes',
                description: 'Impede que remetentes bloqueados avancem na jornada.',
                active: $blacklistAvailable && $activeBlacklistCount > 0,
                editable: $blacklistAvailable,
                status: $this->status($activeBlacklistCount > 0, $blacklistAvailable),
                summary: $activeBlacklistCount > 0
                    ? sprintf('%d remetente(s) bloqueado(s).', $activeBlacklistCount)
                    : 'Nenhum remetente bloqueado.',
                configPreview: [
                    'enabled' => $blacklistAvailable,
                    'available' => $blacklistAvailable,
                    'active_entries_count' => $activeBlacklistCount,
                ],
                branches: [
                    $this->branch('allowed', 'Permitido', 'processing_receive_feedback', true),
                    $this->branch('blocked', 'Bloqueado', 'output_silence', $activeBlacklistCount > 0),
                    $this->branch('default', 'Padrao', 'processing_receive_feedback', true),
                ],
            ),
        ];
    }

    /**
     * @return array<int, EventJourneyNodeData>
     */
    private function processingNodes(
        Event $event,
        array $intakeChannels,
        EventContentModerationSetting $contentModeration,
        EventMediaIntelligenceSetting $mediaIntelligence,
    ): array {
        $aiModeration = $event->isAiModeration();
        $safetyEnabled = $aiModeration && (bool) $contentModeration->enabled;
        $mediaIntelligenceEnabled = $aiModeration && (bool) $mediaIntelligence->enabled;
        $feedbackEnabled = $this->anyReplyCapableChannelActive($intakeChannels);

        return [
            $this->node(
                id: 'processing_receive_feedback',
                stage: 'processing',
                kind: 'process',
                label: 'Confirmar recebimento',
                description: 'Mostra uma reacao inicial quando o canal permite feedback.',
                active: $feedbackEnabled,
                editable: true,
                status: $feedbackEnabled ? 'active' : 'inactive',
                summary: $feedbackEnabled
                    ? 'Envia feedback inicial quando a midia chega.'
                    : 'Sem canal ativo para feedback inicial.',
                configPreview: ['reply_capable_channel_active' => $feedbackEnabled],
                branches: [$this->branch('default', 'Padrao', 'processing_download_media')],
            ),
            $this->node(
                id: 'processing_download_media',
                stage: 'processing',
                kind: 'process',
                label: 'Salvar midia',
                description: 'Baixa e registra a midia recebida.',
                active: true,
                editable: false,
                status: 'required',
                summary: 'Etapa tecnica obrigatoria.',
                configPreview: ['required' => true],
                branches: [$this->branch('default', 'Padrao', 'processing_prepare_variants')],
            ),
            $this->node(
                id: 'processing_prepare_variants',
                stage: 'processing',
                kind: 'process',
                label: 'Preparar variantes',
                description: 'Gera thumbs, previews e arquivos otimizados.',
                active: true,
                editable: false,
                status: 'required',
                summary: 'Prepara a midia para galeria, moderacao e telao.',
                configPreview: ['required' => true],
                branches: [$this->branch('default', 'Padrao', 'processing_safety_ai')],
            ),
            $this->node(
                id: 'processing_safety_ai',
                stage: 'processing',
                kind: 'process',
                label: 'Analisar seguranca com IA',
                description: 'Avalia risco objetivo antes da decisao de publicacao.',
                active: $safetyEnabled,
                editable: true,
                status: $safetyEnabled ? 'active' : 'inactive',
                summary: $this->contentModerationSummary($contentModeration, $aiModeration),
                configPreview: $this->contentModerationPreview($contentModeration),
                branches: [$this->branch('default', 'Padrao', 'processing_media_intelligence')],
            ),
            $this->node(
                id: 'processing_media_intelligence',
                stage: 'processing',
                kind: 'process',
                label: 'Entender contexto e legenda',
                description: 'Usa VLM para enriquecer contexto, legenda, tags e resposta.',
                active: $mediaIntelligenceEnabled,
                editable: true,
                status: $mediaIntelligenceEnabled ? 'active' : 'inactive',
                summary: $mediaIntelligenceEnabled
                    ? 'Analisa contexto visual e textual da midia.'
                    : 'VLM desligado para este evento.',
                configPreview: $this->mediaIntelligencePreview($mediaIntelligence),
                branches: [$this->branch('default', 'Padrao', 'decision_event_moderation_mode')],
            ),
        ];
    }

    /**
     * @return array<int, EventJourneyNodeData>
     */
    private function decisionNodes(
        Event $event,
        EventContentModerationSetting $contentModeration,
        EventMediaIntelligenceSetting $mediaIntelligence,
    ): array {
        $moderationMode = $event->moderation_mode?->value ?? 'manual';
        $safetyActive = $event->isAiModeration() && (bool) $contentModeration->enabled;
        $contextGateActive = $event->isAiModeration()
            && (bool) $mediaIntelligence->enabled
            && $mediaIntelligence->mode === 'gate';

        return [
            $this->node(
                id: 'decision_event_moderation_mode',
                stage: 'decision',
                kind: 'decision',
                label: 'Modo de moderacao',
                description: 'Define se a midia publica direto, passa por revisao humana ou usa IA.',
                active: true,
                editable: true,
                status: 'active',
                summary: $this->moderationModeSummary($moderationMode),
                configPreview: ['moderation_mode' => $moderationMode],
                branches: [
                    $this->branch('approved', 'Aprovado', 'output_gallery', $moderationMode === 'none'),
                    $this->branch('review', 'Revisao', 'output_gallery', $moderationMode === 'manual'),
                    $this->branch('blocked', 'Bloqueado', 'output_silence', false),
                    $this->branch('default', 'Padrao', 'decision_safety_result', $moderationMode === 'ai'),
                ],
            ),
            $this->node(
                id: 'decision_safety_result',
                stage: 'decision',
                kind: 'decision',
                label: 'Resultado Safety',
                description: 'Interpreta sinais objetivos de seguranca quando Safety esta ativo.',
                active: $safetyActive,
                editable: true,
                status: $safetyActive ? 'active' : 'inactive',
                summary: $safetyActive ? 'Pode aprovar, revisar ou bloquear conforme risco.' : 'Sem decisao de Safety ativa.',
                configPreview: $this->contentModerationPreview($contentModeration),
                branches: [
                    $this->branch('safe', 'Seguro', 'decision_context_gate', $safetyActive),
                    $this->branch('review', 'Revisao', 'output_gallery', $safetyActive),
                    $this->branch('blocked', 'Bloqueado', 'output_silence', $safetyActive && $contentModeration->mode === 'enforced'),
                    $this->branch('default', 'Padrao', 'decision_context_gate', true),
                ],
            ),
            $this->node(
                id: 'decision_context_gate',
                stage: 'decision',
                kind: 'decision',
                label: 'Contexto do evento',
                description: 'Usa VLM em modo gate para decidir se a midia combina com o evento.',
                active: $contextGateActive,
                editable: true,
                status: $contextGateActive ? 'active' : 'inactive',
                summary: $contextGateActive
                    ? 'VLM pode aprovar, revisar ou bloquear pelo contexto.'
                    : 'VLM nao esta usando gate de publicacao.',
                configPreview: $this->mediaIntelligencePreview($mediaIntelligence),
                branches: [
                    $this->branch('approved', 'Aprovado', 'output_gallery', $contextGateActive),
                    $this->branch('review', 'Revisao', 'output_gallery', $contextGateActive),
                    $this->branch('blocked', 'Bloqueado', 'output_silence', $contextGateActive),
                    $this->branch('default', 'Padrao', 'decision_media_type', true),
                ],
            ),
            $this->node(
                id: 'decision_media_type',
                stage: 'decision',
                kind: 'decision',
                label: 'Foto ou video',
                description: 'Classifica o tipo da midia para simulacao e politicas futuras.',
                active: true,
                editable: false,
                status: 'required',
                summary: 'Detectado automaticamente pelo pipeline.',
                configPreview: ['source' => 'pipeline'],
                branches: [
                    $this->branch('photo', 'Foto', 'decision_caption_presence', true),
                    $this->branch('video', 'Video', 'decision_caption_presence', true),
                    $this->branch('default', 'Padrao', 'decision_caption_presence', true),
                ],
            ),
            $this->node(
                id: 'decision_caption_presence',
                stage: 'decision',
                kind: 'decision',
                label: 'Legenda existe?',
                description: 'Mostra se a midia chegou com texto/caption para IA e respostas.',
                active: true,
                editable: false,
                status: 'required',
                summary: 'Usado para simulacao e contexto da IA.',
                configPreview: ['source' => 'normalized_text_context'],
                branches: [
                    $this->branch('with_caption', 'Com legenda', 'output_gallery', true),
                    $this->branch('without_caption', 'Sem legenda', 'output_gallery', true),
                    $this->branch('default', 'Padrao', 'output_gallery', true),
                ],
            ),
        ];
    }

    /**
     * @return array<int, EventJourneyNodeData>
     */
    private function outputNodes(
        array $modules,
        EventMediaIntelligenceSetting $mediaIntelligence,
        bool $replyCapableChannelActive,
        bool $wallEnabled,
        bool $wallAvailable,
    ): array {
        $replyTextMode = $mediaIntelligence->resolvedReplyTextMode();
        $replyEnabled = $mediaIntelligence->automaticReplyEnabled();

        return [
            $this->node(
                id: 'output_reaction_final',
                stage: 'output',
                kind: 'output',
                label: 'Reacao final',
                description: 'Envia uma reacao de aprovado, pendente ou recusado quando o canal permite.',
                active: $replyCapableChannelActive,
                editable: true,
                status: $replyCapableChannelActive ? 'active' : 'inactive',
                summary: $replyCapableChannelActive
                    ? 'Pode enviar feedback final no canal de origem.'
                    : 'Sem canal ativo para feedback final.',
                configPreview: ['reply_capable_channel_active' => $replyCapableChannelActive],
            ),
            $this->node(
                id: 'output_reply_text',
                stage: 'output',
                kind: 'output',
                label: 'Responder com mensagem',
                description: 'Envia resposta fixa ou por IA apos a publicacao.',
                active: $replyEnabled,
                editable: true,
                status: $replyEnabled ? 'active' : 'inactive',
                summary: match ($replyTextMode) {
                    'ai' => 'Responde usando texto gerado por IA.',
                    'fixed_random' => 'Responde usando um template fixo sorteado.',
                    default => 'Resposta textual desligada.',
                },
                configPreview: [
                    'reply_text_enabled' => $replyEnabled,
                    'reply_text_mode' => $replyTextMode,
                    'template_count' => count((array) ($mediaIntelligence->reply_fixed_templates_json ?? [])),
                ],
            ),
            $this->node(
                id: 'output_gallery',
                stage: 'output',
                kind: 'output',
                label: 'Publicar na galeria',
                description: 'Disponibiliza midia aprovada na galeria do evento.',
                active: true,
                editable: false,
                status: 'required',
                summary: 'Destino base do fluxo de midia.',
                configPreview: ['required' => true],
            ),
            $this->node(
                id: 'output_wall',
                stage: 'output',
                kind: 'output',
                label: 'Enviar para telao',
                description: 'Deixa midias publicadas disponiveis para o wall.',
                active: $wallEnabled,
                editable: true,
                status: $this->status($wallEnabled, $wallAvailable),
                summary: $wallEnabled ? 'Telao ativo para midias publicadas.' : 'Telao desligado ou indisponivel.',
                configPreview: [
                    'module_enabled' => (bool) ($modules['wall'] ?? false),
                    'available' => $wallAvailable,
                    'enabled' => $wallEnabled,
                ],
            ),
            $this->node(
                id: 'output_print',
                stage: 'output',
                kind: 'output',
                label: 'Enviar para impressao',
                description: 'Saida de impressao/rolo ainda nao implementada na V1.',
                active: false,
                editable: false,
                status: 'unavailable',
                summary: 'Fora da V1 do builder.',
                configPreview: ['available' => false, 'enabled' => false],
            ),
            $this->node(
                id: 'output_silence',
                stage: 'output',
                kind: 'output',
                label: 'Silenciar ou arquivar',
                description: 'Caminho usado quando o conteudo nao deve receber resposta final.',
                active: true,
                editable: false,
                status: 'required',
                summary: 'Fallback seguro para bloqueio ou origem sem resposta.',
                configPreview: ['required' => true],
            ),
        ];
    }

    /**
     * @return array<string, EventJourneyCapabilityData>
     */
    private function buildCapabilities(
        Event $event,
        array $entitlements,
        array $modules,
        EventMediaIntelligenceSetting $mediaIntelligence,
        bool $wallEnabled,
        bool $wallAvailable,
    ): array {
        return [
            'supports_react_flow_visual_mode' => new EventJourneyCapabilityData(
                id: 'supports_react_flow_visual_mode',
                label: 'Visualizacao em fluxo',
                enabled: true,
                available: true,
                editable: false,
            ),
            'supports_manual_review' => new EventJourneyCapabilityData(
                id: 'supports_manual_review',
                label: 'Revisao manual',
                enabled: $event->isManualModeration() || $event->isAiModeration(),
                available: true,
                editable: true,
            ),
            'supports_ai_reply' => new EventJourneyCapabilityData(
                id: 'supports_ai_reply',
                label: 'Resposta por IA',
                enabled: $mediaIntelligence->usesAiAutomaticReply(),
                available: true,
                editable: true,
                config_preview: ['reply_text_mode' => $mediaIntelligence->resolvedReplyTextMode()],
            ),
            'supports_wall_output' => new EventJourneyCapabilityData(
                id: 'supports_wall_output',
                label: 'Telao',
                enabled: $wallEnabled,
                available: $wallAvailable,
                editable: true,
                reason: $wallAvailable ? null : 'Evento sem entitlement de telao.',
                config_preview: ['module_enabled' => (bool) ($modules['wall'] ?? false)],
            ),
            'supports_sender_blacklist' => new EventJourneyCapabilityData(
                id: 'supports_sender_blacklist',
                label: 'Bloqueio de remetentes',
                enabled: $this->entitlementBoolean($entitlements, 'channels.blacklist.enabled', true),
                available: $this->entitlementBoolean($entitlements, 'channels.blacklist.enabled', true),
                editable: true,
            ),
            'supports_print' => new EventJourneyCapabilityData(
                id: 'supports_print',
                label: 'Impressao',
                enabled: false,
                available: false,
                editable: false,
                reason: 'Fora da V1 do builder.',
            ),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildWarnings(Event $event, array $intakeChannels, array $entitlements): array
    {
        $warnings = [];

        foreach ([
            'whatsapp_groups' => ['label' => 'WhatsApp grupos', 'path' => 'channels.whatsapp_groups.enabled'],
            'whatsapp_direct' => ['label' => 'WhatsApp privado', 'path' => 'channels.whatsapp_direct.enabled'],
            'telegram' => ['label' => 'Telegram', 'path' => 'channels.telegram.enabled'],
            'public_upload' => ['label' => 'Link de envio', 'path' => 'channels.public_upload.enabled'],
        ] as $channelKey => $channel) {
            if ((bool) data_get($intakeChannels, "{$channelKey}.enabled", false)
                && ! $this->entitlementBoolean($entitlements, $channel['path'], true)
            ) {
                $warnings[] = sprintf(
                    'O canal %s esta ativo, mas o evento nao tem entitlement para usa-lo.',
                    $channel['label'],
                );
            }
        }

        if ($this->needsWhatsAppInstance($intakeChannels) && $event->default_whatsapp_instance_id === null) {
            $warnings[] = 'Ha canais de WhatsApp ativos sem instancia WhatsApp padrao configurada.';
        }

        return array_values(array_unique($warnings));
    }

    /**
     * @return array<string, bool>
     */
    private function moduleStates(Event $event): array
    {
        $states = [
            'live' => false,
            'wall' => false,
            'play' => false,
            'hub' => false,
        ];

        foreach ($event->modules as $module) {
            if (array_key_exists($module->module_key, $states)) {
                $states[$module->module_key] = (bool) $module->is_enabled;
            }
        }

        return $states;
    }

    private function node(
        string $id,
        string $stage,
        string $kind,
        string $label,
        string $description,
        bool $active,
        bool $editable,
        string $status,
        string $summary,
        array $configPreview = [],
        array $branches = [],
        array $warnings = [],
        array $meta = [],
    ): EventJourneyNodeData {
        return new EventJourneyNodeData(
            id: $id,
            stage: $stage,
            kind: $kind,
            label: $label,
            description: $description,
            active: $active,
            editable: $editable,
            status: $status,
            summary: $summary,
            config_preview: $configPreview,
            branches: $branches,
            warnings: $warnings,
            meta: $meta,
        );
    }

    private function branch(
        string $id,
        string $label,
        ?string $targetNodeId = null,
        bool $active = true,
        string $status = 'active',
        ?string $summary = null,
        array $conditions = [],
    ): EventJourneyBranchData {
        return new EventJourneyBranchData(
            id: $id,
            label: $label,
            target_node_id: $targetNodeId,
            active: $active,
            status: $active ? $status : 'inactive',
            summary: $summary,
            conditions: $conditions,
        );
    }

    private function status(bool $enabled, bool $available = true): string
    {
        if (! $available) {
            return 'locked';
        }

        return $enabled ? 'active' : 'inactive';
    }

    private function moderationModeSummary(string $mode): string
    {
        return match ($mode) {
            'none' => 'Aprova automaticamente sem fila manual.',
            'ai' => 'Usa politicas de IA para aprovar, revisar ou bloquear.',
            default => 'Envia midias para revisao humana antes de publicar.',
        };
    }

    private function contentModerationSummary(EventContentModerationSetting $settings, bool $aiModeration): string
    {
        if (! $aiModeration || ! (bool) $settings->enabled) {
            return 'Safety por IA desligado.';
        }

        if ($settings->mode === 'observe_only') {
            return 'Analisa risco e registra sinais sem bloquear automaticamente.';
        }

        return 'Bloqueia ou envia para revisao conforme risco detectado.';
    }

    /**
     * @return array<string, mixed>
     */
    private function contentModerationPreview(EventContentModerationSetting $settings): array
    {
        return [
            'enabled' => (bool) $settings->enabled,
            'mode' => $settings->mode,
            'fallback_mode' => $settings->fallback_mode,
            'provider_key' => $settings->provider_key,
            'analysis_scope' => $settings->analysis_scope,
            'normalized_text_context_mode' => $settings->normalized_text_context_mode,
            'inherits_global' => (bool) ($settings->inherits_global ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaIntelligencePreview(EventMediaIntelligenceSetting $settings): array
    {
        return [
            'enabled' => (bool) $settings->enabled,
            'mode' => $settings->mode,
            'fallback_mode' => $settings->fallback_mode,
            'provider_key' => $settings->provider_key,
            'model_key' => $settings->model_key,
            'reply_text_enabled' => $settings->automaticReplyEnabled(),
            'reply_text_mode' => $settings->resolvedReplyTextMode(),
            'context_scope' => $settings->context_scope,
            'reply_scope' => $settings->reply_scope,
            'normalized_text_context_mode' => $settings->normalized_text_context_mode,
            'inherits_global' => (bool) ($settings->inherits_global ?? false),
        ];
    }

    private function anyReplyCapableChannelActive(array $intakeChannels): bool
    {
        return (bool) data_get($intakeChannels, 'whatsapp_direct.enabled', false)
            || (bool) data_get($intakeChannels, 'whatsapp_groups.enabled', false)
            || (bool) data_get($intakeChannels, 'telegram.enabled', false);
    }

    private function needsWhatsAppInstance(array $intakeChannels): bool
    {
        return (bool) data_get($intakeChannels, 'whatsapp_direct.enabled', false)
            || (bool) data_get($intakeChannels, 'whatsapp_groups.enabled', false);
    }

    private function entitlementBoolean(array $entitlements, string $path, bool $default): bool
    {
        $cursor = $entitlements;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return $default;
            }

            $cursor = $cursor[$segment];
        }

        return (bool) $cursor;
    }

    /**
     * @return array<int, EventJourneyScenarioData>
     */
    private function simulationPresets(): array
    {
        return [
            new EventJourneyScenarioData(
                id: 'photo_whatsapp_private_with_caption',
                label: 'Foto com legenda',
                description: 'Simula uma foto recebida pelo WhatsApp privado com legenda.',
                input: ['channel' => 'whatsapp_direct', 'media_type' => 'photo', 'has_caption' => true],
                expected_node_ids: [
                    'entry_whatsapp_direct',
                    'processing_download_media',
                    'decision_caption_presence',
                    'output_gallery',
                ],
            ),
            new EventJourneyScenarioData(
                id: 'video_without_caption',
                label: 'Video sem legenda',
                description: 'Simula um video recebido sem texto de apoio.',
                input: ['channel' => 'public_upload', 'media_type' => 'video', 'has_caption' => false],
                expected_node_ids: [
                    'entry_public_upload',
                    'processing_download_media',
                    'decision_media_type',
                    'output_gallery',
                ],
            ),
            new EventJourneyScenarioData(
                id: 'blocked_sender',
                label: 'Remetente bloqueado',
                description: 'Simula uma origem bloqueada antes do processamento.',
                input: ['channel' => 'whatsapp_direct', 'sender_blocked' => true],
                expected_node_ids: [
                    'entry_sender_blacklist',
                    'output_silence',
                ],
            ),
        ];
    }

}
