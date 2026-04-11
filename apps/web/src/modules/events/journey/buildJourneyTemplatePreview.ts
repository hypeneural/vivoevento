import type {
  ApiEventContentModerationSettings,
  ApiEventMediaIntelligenceSettings,
} from '@/lib/api-types';

import type {
  EventJourneyBranch,
  EventJourneyContentModerationPatch,
  EventJourneyMediaIntelligencePatch,
  EventJourneyNode,
  EventJourneyProjection,
  EventJourneyUpdatePayload,
} from './types';

export type JourneyTemplateId =
  | 'direct-approval'
  | 'manual-review'
  | 'ai-moderating'
  | 'hybrid-ai-human'
  | 'social-simple'
  | 'corporate-controlled';

export interface JourneyTemplateDefinition {
  id: JourneyTemplateId;
  label: string;
  description: string;
}

export interface JourneyTemplateDiffItem {
  id: string;
  kind: 'applied' | 'skipped';
  label: string;
  description: string;
}

export interface JourneyTemplatePreview {
  template: JourneyTemplateDefinition;
  payload: EventJourneyUpdatePayload;
  diff: JourneyTemplateDiffItem[];
  previewProjection: EventJourneyProjection;
}

export const JOURNEY_TEMPLATE_DEFINITIONS: JourneyTemplateDefinition[] = [
  {
    id: 'direct-approval',
    label: 'Aprovacao direta',
    description: 'Publica sem fila manual nem analises de IA.',
  },
  {
    id: 'manual-review',
    label: 'Revisao manual',
    description: 'Toda midia passa por operador antes de publicar.',
  },
  {
    id: 'ai-moderating',
    label: 'IA moderando',
    description: 'Liga Safety, contexto por IA e resposta automatica por IA.',
  },
  {
    id: 'hybrid-ai-human',
    label: 'Hibrido IA + humano',
    description: 'Usa IA para priorizar e enriquecer, mantendo a trilha de revisao mais conservadora.',
  },
  {
    id: 'social-simple',
    label: 'Evento social simples',
    description: 'Prioriza recebimento facil, IA leve e publicacao mais viva para eventos sociais.',
  },
  {
    id: 'corporate-controlled',
    label: 'Evento corporativo controlado',
    description: 'Fecha entradas abertas e centraliza a decisao em uma trilha mais controlada.',
  },
];

function cloneProjection(projection: EventJourneyProjection): EventJourneyProjection {
  return JSON.parse(JSON.stringify(projection)) as EventJourneyProjection;
}

function findNode(projection: EventJourneyProjection, nodeId: string): EventJourneyNode | null {
  for (const stage of projection.stages) {
    const node = stage.nodes.find((candidate) => candidate.id === nodeId);

    if (node) {
      return node;
    }
  }

  return null;
}

function status(enabled: boolean, available = true) {
  if (!available) {
    return 'locked' as const;
  }

  return enabled ? 'active' as const : 'inactive' as const;
}

function branchState(branch: EventJourneyBranch, active: boolean): EventJourneyBranch {
  return {
    ...branch,
    active,
    status: active ? 'active' : 'inactive',
  };
}

function updateNode(
  projection: EventJourneyProjection,
  nodeId: string,
  updater: (node: EventJourneyNode) => EventJourneyNode,
) {
  for (const stage of projection.stages) {
    const index = stage.nodes.findIndex((candidate) => candidate.id === nodeId);

    if (index >= 0) {
      stage.nodes[index] = updater(stage.nodes[index]);
      return;
    }
  }
}

function humanList(items: string[]) {
  if (items.length === 1) {
    return items[0];
  }

  if (items.length === 2) {
    return `${items[0]} e ${items[1]}`;
  }

  const head = items.slice(0, -1);
  const tail = items[items.length - 1];

  return `${head.join(', ')} e ${tail}`;
}

function activeChannelLabels(projection: EventJourneyProjection) {
  return [
    projection.intake_channels.whatsapp_direct.enabled ? 'WhatsApp privado' : null,
    projection.intake_channels.whatsapp_groups.enabled ? 'grupos de WhatsApp' : null,
    projection.intake_channels.telegram.enabled ? 'Telegram' : null,
    projection.intake_channels.public_upload.enabled ? 'link de envio' : null,
  ].filter((value): value is string => Boolean(value));
}

function decisionClause(projection: EventJourneyProjection) {
  const moderationMode = projection.settings.moderation_mode ?? 'manual';
  const safety = projection.settings.content_moderation;
  const media = projection.settings.media_intelligence;

  if (moderationMode === 'none') {
    return 'aprova automaticamente';
  }

  if (moderationMode === 'manual') {
    return 'envia para revisao manual';
  }

  if (safety.enabled && safety.mode === 'enforced' && media.enabled && media.mode === 'gate') {
    return 'analisa risco e contexto com IA antes de publicar';
  }

  if (safety.enabled && safety.mode === 'enforced') {
    return 'analisa risco com IA antes de publicar';
  }

  if (safety.enabled && safety.mode === 'observe_only' && media.enabled) {
    return 'usa IA para entender melhor a midia e sinalizar revisao quando necessario';
  }

  if (safety.enabled && safety.mode === 'observe_only') {
    return 'usa IA para sinalizar revisao quando necessario';
  }

  if (media.enabled && media.mode === 'gate') {
    return 'analisa o contexto com IA antes de publicar';
  }

  if (media.enabled) {
    return 'usa IA para entender melhor a midia antes da decisao';
  }

  return 'segue em modo IA, mas ainda sem analises ativas';
}

function replyClause(projection: EventJourneyProjection) {
  const replyCapableChannelActive = projection.intake_channels.whatsapp_direct.enabled
    || projection.intake_channels.whatsapp_groups.enabled
    || projection.intake_channels.telegram.enabled;
  const media = projection.settings.media_intelligence;
  const replyEnabled = media.reply_text_enabled && media.reply_text_mode !== 'disabled';

  if (!replyCapableChannelActive || !replyEnabled) {
    return 'nao envia resposta automatica';
  }

  if (media.reply_text_mode === 'ai') {
    return 'responde automaticamente com IA';
  }

  if (media.reply_text_mode === 'fixed_random') {
    return 'responde com mensagem pronta';
  }

  return 'nao envia resposta automatica';
}

function outputClause(projection: EventJourneyProjection) {
  const destinations = projection.settings.destinations;

  if (destinations.gallery && destinations.wall) {
    return 'publica na galeria e no telao';
  }

  if (destinations.gallery) {
    return 'publica na galeria';
  }

  if (destinations.wall) {
    return 'envia para o telao';
  }

  return 'nao publica em destinos visiveis';
}

function buildHumanSummary(projection: EventJourneyProjection) {
  const channels = activeChannelLabels(projection);

  if (channels.length === 0) {
    return 'Sem canais de recebimento ativos, a jornada fica pronta para configuracao e nenhuma midia nova entra no fluxo.';
  }

  return `Quando a midia chega por ${humanList(channels)}, o Evento Vivo ${decisionClause(projection)}, ${replyClause(projection)} e ${outputClause(projection)}.`;
}

function contentModerationSummary(projection: EventJourneyProjection) {
  const aiModeration = projection.settings.moderation_mode === 'ai';
  const settings = projection.settings.content_moderation;

  if (!aiModeration || !settings.enabled) {
    return 'Safety por IA desligado.';
  }

  if (settings.mode === 'observe_only') {
    return 'Analisa risco e registra sinais sem bloquear automaticamente.';
  }

  return 'Bloqueia ou envia para revisao conforme risco detectado.';
}

function mediaReplySummary(projection: EventJourneyProjection) {
  const media = projection.settings.media_intelligence;
  const replyEnabled = media.reply_text_enabled && media.reply_text_mode !== 'disabled';

  if (!replyEnabled) {
    return 'Resposta textual desligada.';
  }

  if (media.reply_text_mode === 'ai') {
    return 'Responde usando texto gerado por IA.';
  }

  if (media.reply_text_mode === 'fixed_random') {
    return 'Responde usando um template fixo sorteado.';
  }

  return 'Resposta textual desligada.';
}

function nodeAvailable(node: EventJourneyNode | null, fallback = true) {
  const available = node?.config_preview?.available;

  return typeof available === 'boolean' ? available : fallback;
}

function setChannelSummaryAndStatus(preview: EventJourneyProjection) {
  const directNode = findNode(preview, 'entry_whatsapp_direct');
  const groupsNode = findNode(preview, 'entry_whatsapp_groups');
  const telegramNode = findNode(preview, 'entry_telegram');
  const uploadNode = findNode(preview, 'entry_public_upload');
  const blacklistNode = findNode(preview, 'entry_sender_blacklist');

  const directAvailable = nodeAvailable(directNode);
  const groupsAvailable = nodeAvailable(groupsNode);
  const telegramAvailable = nodeAvailable(telegramNode);
  const uploadAvailable = nodeAvailable(uploadNode);
  const blacklistAvailable = nodeAvailable(blacklistNode);
  const directEnabled = preview.intake_channels.whatsapp_direct.enabled;
  const groupsEnabled = preview.intake_channels.whatsapp_groups.enabled;
  const telegramEnabled = preview.intake_channels.telegram.enabled;
  const uploadEnabled = preview.intake_channels.public_upload.enabled;
  const blacklistCount = typeof blacklistNode?.config_preview?.active_entries_count === 'number'
    ? blacklistNode.config_preview.active_entries_count
    : 0;

  updateNode(preview, 'entry_whatsapp_direct', (node) => ({
    ...node,
    active: directEnabled && directAvailable,
    status: status(directEnabled, directAvailable),
    summary: directEnabled ? 'Recebe midias por codigo privado.' : 'WhatsApp privado desligado.',
    config_preview: {
      ...node.config_preview,
      enabled: directEnabled,
      available: directAvailable,
      media_inbox_code: preview.intake_channels.whatsapp_direct.media_inbox_code,
      session_ttl_minutes: preview.intake_channels.whatsapp_direct.session_ttl_minutes,
    },
  }));

  updateNode(preview, 'entry_whatsapp_groups', (node) => ({
    ...node,
    active: groupsEnabled && groupsAvailable,
    status: status(groupsEnabled, groupsAvailable),
    summary: groupsEnabled
      ? `Recebe midias de ${preview.intake_channels.whatsapp_groups.groups.length} grupo(s).`
      : 'WhatsApp grupos desligado.',
    config_preview: {
      ...node.config_preview,
      enabled: groupsEnabled,
      available: groupsAvailable,
      group_count: preview.intake_channels.whatsapp_groups.groups.length,
    },
  }));

  updateNode(preview, 'entry_telegram', (node) => ({
    ...node,
    active: telegramEnabled && telegramAvailable,
    status: status(telegramEnabled, telegramAvailable),
    summary: telegramEnabled ? 'Recebe midias pelo bot do Telegram.' : 'Telegram desligado.',
    config_preview: {
      ...node.config_preview,
      enabled: telegramEnabled,
      available: telegramAvailable,
      bot_username: preview.intake_channels.telegram.bot_username,
      media_inbox_code: preview.intake_channels.telegram.media_inbox_code,
      session_ttl_minutes: preview.intake_channels.telegram.session_ttl_minutes,
    },
  }));

  updateNode(preview, 'entry_public_upload', (node) => ({
    ...node,
    active: uploadEnabled && uploadAvailable,
    status: status(uploadEnabled, uploadAvailable),
    summary: uploadEnabled ? 'Recebe midias pelo link publico do evento.' : 'Link de envio desligado.',
    config_preview: {
      ...node.config_preview,
      enabled: uploadEnabled,
      available: uploadAvailable,
    },
  }));

  updateNode(preview, 'entry_sender_blacklist', (node) => ({
    ...node,
    active: blacklistAvailable && blacklistCount > 0,
    status: status(blacklistCount > 0, blacklistAvailable),
    summary: blacklistCount > 0
      ? `${blacklistCount} remetente(s) bloqueado(s).`
      : 'Nenhum remetente bloqueado.',
    config_preview: {
      ...node.config_preview,
      enabled: blacklistAvailable,
      available: blacklistAvailable,
      active_entries_count: blacklistCount,
    },
    branches: node.branches.map((branch) => {
      if (branch.id === 'blocked') {
        return branchState(branch, blacklistCount > 0);
      }

      return branchState(branch, true);
    }),
  }));
}

function setProcessingSummaryAndStatus(preview: EventJourneyProjection) {
  const replyCapableChannelActive = preview.intake_channels.whatsapp_direct.enabled
    || preview.intake_channels.whatsapp_groups.enabled
    || preview.intake_channels.telegram.enabled;
  const aiModeration = preview.settings.moderation_mode === 'ai';
  const safetyEnabled = aiModeration && preview.settings.content_moderation.enabled;
  const mediaEnabled = aiModeration && preview.settings.media_intelligence.enabled;

  updateNode(preview, 'processing_receive_feedback', (node) => ({
    ...node,
    active: replyCapableChannelActive,
    status: replyCapableChannelActive ? 'active' : 'inactive',
    summary: replyCapableChannelActive
      ? 'Envia feedback inicial quando a midia chega.'
      : 'Sem canal ativo para feedback inicial.',
    config_preview: {
      ...node.config_preview,
      reply_capable_channel_active: replyCapableChannelActive,
    },
  }));

  updateNode(preview, 'processing_safety_ai', (node) => ({
    ...node,
    active: safetyEnabled,
    status: safetyEnabled ? 'active' : 'inactive',
    summary: contentModerationSummary(preview),
    config_preview: {
      ...node.config_preview,
      ...preview.settings.content_moderation,
    },
  }));

  updateNode(preview, 'processing_media_intelligence', (node) => ({
    ...node,
    active: mediaEnabled,
    status: mediaEnabled ? 'active' : 'inactive',
    summary: mediaEnabled
      ? 'Analisa contexto visual e textual da midia.'
      : 'VLM desligado para este evento.',
    config_preview: {
      ...node.config_preview,
      ...preview.settings.media_intelligence,
    },
  }));
}

function setDecisionSummaryAndStatus(preview: EventJourneyProjection) {
  const moderationMode = preview.settings.moderation_mode ?? 'manual';
  const aiModeration = moderationMode === 'ai';
  const safetyEnabled = aiModeration && preview.settings.content_moderation.enabled;
  const contextGateActive = aiModeration
    && preview.settings.media_intelligence.enabled
    && preview.settings.media_intelligence.mode === 'gate';

  updateNode(preview, 'decision_event_moderation_mode', (node) => ({
    ...node,
    summary: moderationMode === 'none'
      ? 'Aprova automaticamente sem fila manual.'
      : moderationMode === 'ai'
        ? 'Usa politicas de IA para aprovar, revisar ou bloquear.'
        : 'Envia midias para revisao humana antes de publicar.',
    config_preview: {
      ...node.config_preview,
      moderation_mode: moderationMode,
    },
    branches: node.branches.map((branch) => {
      if (branch.id === 'approved') {
        return branchState(branch, moderationMode === 'none');
      }

      if (branch.id === 'review') {
        return branchState(branch, moderationMode === 'manual');
      }

      if (branch.id === 'blocked') {
        return branchState(branch, false);
      }

      if (branch.id === 'default') {
        return branchState(branch, moderationMode === 'ai');
      }

      return branch;
    }),
  }));

  updateNode(preview, 'decision_safety_result', (node) => ({
    ...node,
    active: safetyEnabled,
    status: safetyEnabled ? 'active' : 'inactive',
    summary: safetyEnabled
      ? 'Pode aprovar, revisar ou bloquear conforme risco.'
      : 'Sem decisao de Safety ativa.',
    config_preview: {
      ...node.config_preview,
      ...preview.settings.content_moderation,
    },
    branches: node.branches.map((branch) => {
      if (branch.id === 'safe') {
        return branchState(branch, safetyEnabled);
      }

      if (branch.id === 'review') {
        return branchState(branch, safetyEnabled);
      }

      if (branch.id === 'blocked') {
        return branchState(branch, safetyEnabled && preview.settings.content_moderation.mode === 'enforced');
      }

      if (branch.id === 'default') {
        return branchState(branch, true);
      }

      return branch;
    }),
  }));

  updateNode(preview, 'decision_context_gate', (node) => ({
    ...node,
    active: contextGateActive,
    status: contextGateActive ? 'active' : 'inactive',
    summary: contextGateActive
      ? 'VLM pode aprovar, revisar ou bloquear pelo contexto.'
      : 'VLM nao esta usando gate de publicacao.',
    config_preview: {
      ...node.config_preview,
      ...preview.settings.media_intelligence,
    },
    branches: node.branches.map((branch) => {
      if (branch.id === 'approved' || branch.id === 'review' || branch.id === 'blocked') {
        return branchState(branch, contextGateActive);
      }

      if (branch.id === 'default') {
        return branchState(branch, true);
      }

      return branch;
    }),
  }));
}

function setOutputSummaryAndStatus(preview: EventJourneyProjection) {
  const replyCapableChannelActive = preview.intake_channels.whatsapp_direct.enabled
    || preview.intake_channels.whatsapp_groups.enabled
    || preview.intake_channels.telegram.enabled;
  const replyEnabled = preview.settings.media_intelligence.reply_text_enabled
    && preview.settings.media_intelligence.reply_text_mode !== 'disabled';
  const wallCapability = preview.capabilities.supports_wall_output;
  const wallAvailable = wallCapability?.available ?? true;
  const wallEnabled = preview.settings.modules.wall && wallAvailable;

  preview.settings.destinations = {
    ...preview.settings.destinations,
    gallery: true,
    wall: wallEnabled,
    print: false,
  };

  updateNode(preview, 'output_reaction_final', (node) => ({
    ...node,
    active: replyCapableChannelActive,
    status: replyCapableChannelActive ? 'active' : 'inactive',
    summary: replyCapableChannelActive
      ? 'Pode enviar feedback final no canal de origem.'
      : 'Sem canal ativo para feedback final.',
    config_preview: {
      ...node.config_preview,
      reply_capable_channel_active: replyCapableChannelActive,
    },
  }));

  updateNode(preview, 'output_reply_text', (node) => ({
    ...node,
    active: replyEnabled,
    status: replyEnabled ? 'active' : 'inactive',
    summary: mediaReplySummary(preview),
    config_preview: {
      ...node.config_preview,
      reply_text_enabled: replyEnabled,
      reply_text_mode: preview.settings.media_intelligence.reply_text_mode,
    },
  }));

  updateNode(preview, 'output_wall', (node) => ({
    ...node,
    active: wallEnabled,
    status: status(wallEnabled, wallAvailable),
    summary: wallEnabled ? 'Telao ativo para midias publicadas.' : 'Telao desligado ou indisponivel.',
    config_preview: {
      ...node.config_preview,
      module_enabled: preview.settings.modules.wall,
      available: wallAvailable,
      enabled: wallEnabled,
    },
  }));

  if (preview.capabilities.supports_ai_reply) {
    preview.capabilities.supports_ai_reply = {
      ...preview.capabilities.supports_ai_reply,
      enabled: preview.settings.media_intelligence.reply_text_mode === 'ai'
        && preview.settings.media_intelligence.reply_text_enabled,
      config_preview: {
        ...preview.capabilities.supports_ai_reply.config_preview,
        reply_text_mode: preview.settings.media_intelligence.reply_text_mode,
      },
    };
  }

  if (preview.capabilities.supports_manual_review) {
    preview.capabilities.supports_manual_review = {
      ...preview.capabilities.supports_manual_review,
      enabled: preview.settings.moderation_mode === 'manual' || preview.settings.moderation_mode === 'ai',
    };
  }

  if (preview.capabilities.supports_wall_output) {
    preview.capabilities.supports_wall_output = {
      ...preview.capabilities.supports_wall_output,
      enabled: wallEnabled,
      config_preview: {
        ...preview.capabilities.supports_wall_output.config_preview,
        module_enabled: preview.settings.modules.wall,
      },
    };
  }
}

function buildWarnings(preview: EventJourneyProjection) {
  const warnings = new Set<string>();
  const entryLabels: Record<string, string> = {
    entry_whatsapp_groups: 'WhatsApp grupos',
    entry_whatsapp_direct: 'WhatsApp privado',
    entry_telegram: 'Telegram',
    entry_public_upload: 'Link de envio',
  };

  (
    [
      ['entry_whatsapp_groups', preview.intake_channels.whatsapp_groups.enabled],
      ['entry_whatsapp_direct', preview.intake_channels.whatsapp_direct.enabled],
      ['entry_telegram', preview.intake_channels.telegram.enabled],
      ['entry_public_upload', preview.intake_channels.public_upload.enabled],
    ] as const
  ).forEach(([nodeId, enabled]) => {
    const node = findNode(preview, nodeId);

    if (enabled && node?.status === 'locked') {
      warnings.add(`O canal ${entryLabels[nodeId]} esta ativo, mas o evento nao tem entitlement para usa-lo.`);
    }
  });

  if (
    (preview.intake_channels.whatsapp_direct.enabled || preview.intake_channels.whatsapp_groups.enabled)
    && preview.intake_defaults.whatsapp_instance_id === null
  ) {
    warnings.add('Ha canais de WhatsApp ativos sem instancia WhatsApp padrao configurada.');
  }

  return Array.from(warnings);
}

function applyJourneyPreviewPayload(
  projection: EventJourneyProjection,
  payload: EventJourneyUpdatePayload,
) {
  const preview = cloneProjection(projection);

  if (payload.moderation_mode !== undefined) {
    preview.settings.moderation_mode = payload.moderation_mode;
    preview.event.moderation_mode = payload.moderation_mode;
  }

  if (payload.modules) {
    preview.settings.modules = {
      ...preview.settings.modules,
      ...payload.modules,
    };
    preview.event.modules = {
      ...preview.event.modules,
      ...payload.modules,
    };
  }

  if (payload.intake_defaults) {
    preview.intake_defaults = {
      ...preview.intake_defaults,
      ...payload.intake_defaults,
    };
  }

  if (payload.intake_channels?.whatsapp_groups) {
    preview.intake_channels.whatsapp_groups = {
      ...preview.intake_channels.whatsapp_groups,
      ...payload.intake_channels.whatsapp_groups,
    };
  }

  if (payload.intake_channels?.whatsapp_direct) {
    preview.intake_channels.whatsapp_direct = {
      ...preview.intake_channels.whatsapp_direct,
      ...payload.intake_channels.whatsapp_direct,
    };
  }

  if (payload.intake_channels?.public_upload) {
    preview.intake_channels.public_upload = {
      ...preview.intake_channels.public_upload,
      ...payload.intake_channels.public_upload,
    };
  }

  if (payload.intake_channels?.telegram) {
    preview.intake_channels.telegram = {
      ...preview.intake_channels.telegram,
      ...payload.intake_channels.telegram,
    };
  }

  if (payload.content_moderation) {
    preview.settings.content_moderation = {
      ...preview.settings.content_moderation,
      ...payload.content_moderation,
    };
  }

  if (payload.media_intelligence) {
    preview.settings.media_intelligence = {
      ...preview.settings.media_intelligence,
      ...payload.media_intelligence,
      reply_text_enabled: payload.media_intelligence.reply_text_enabled
        ?? preview.settings.media_intelligence.reply_text_enabled,
      reply_text_mode: payload.media_intelligence.reply_text_mode
        ?? preview.settings.media_intelligence.reply_text_mode,
    };
  }

  setChannelSummaryAndStatus(preview);
  setProcessingSummaryAndStatus(preview);
  setDecisionSummaryAndStatus(preview);
  setOutputSummaryAndStatus(preview);
  preview.warnings = buildWarnings(preview);
  preview.summary = {
    human_text: buildHumanSummary(preview),
  };

  return preview;
}

function applyDiff(diff: JourneyTemplateDiffItem[], id: string, label: string, description: string) {
  diff.push({
    id,
    kind: 'applied',
    label,
    description,
  });
}

function skipDiff(diff: JourneyTemplateDiffItem[], id: string, label: string, description: string) {
  diff.push({
    id,
    kind: 'skipped',
    label,
    description,
  });
}

function setNestedPatch<T extends Record<string, unknown>>(target: T, key: keyof T, value: unknown) {
  target[key] = value as T[keyof T];
}

function channelAvailability(projection: EventJourneyProjection, nodeId: string) {
  return nodeAvailable(findNode(projection, nodeId));
}

function buildTemplatePayload(
  projection: EventJourneyProjection,
  template: JourneyTemplateDefinition,
): { payload: EventJourneyUpdatePayload; diff: JourneyTemplateDiffItem[] } {
  const payload: EventJourneyUpdatePayload = {};
  const diff: JourneyTemplateDiffItem[] = [];
  const wallAvailable = projection.capabilities.supports_wall_output?.available ?? true;
  const whatsappDirectAvailable = channelAvailability(projection, 'entry_whatsapp_direct');
  const whatsappGroupsAvailable = channelAvailability(projection, 'entry_whatsapp_groups');
  const uploadAvailable = channelAvailability(projection, 'entry_public_upload');

  const setModerationMode = (next: EventJourneyProjection['settings']['moderation_mode']) => {
    if (projection.settings.moderation_mode === next) {
      return;
    }

    payload.moderation_mode = next;
    applyDiff(
      diff,
      `moderation-${String(next)}`,
      'Modo de moderacao',
      next === 'none'
        ? 'Troca a decisao principal para aprovacao direta.'
        : next === 'manual'
          ? 'Troca a decisao principal para revisao manual.'
          : 'Liga a trilha principal de moderacao por IA.',
    );
  };

  const ensureContentModeration = () => {
    payload.content_moderation ??= {};
    return payload.content_moderation as EventJourneyContentModerationPatch;
  };

  const ensureMediaIntelligence = () => {
    payload.media_intelligence ??= {};
    return payload.media_intelligence as EventJourneyMediaIntelligencePatch;
  };

  const setContentModeration = (next: Partial<EventJourneyContentModerationPatch>, description: string) => {
    const current = projection.settings.content_moderation;
    const hasChange = Object.entries(next).some(([key, value]) => current[key as keyof typeof current] !== value);

    if (!hasChange) {
      return;
    }

    Object.entries(next).forEach(([key, value]) => {
      setNestedPatch(ensureContentModeration(), key as keyof EventJourneyContentModerationPatch, value);
    });
    applyDiff(diff, `content-${description}`, 'Safety', description);
  };

  const setMediaIntelligence = (next: Partial<EventJourneyMediaIntelligencePatch>, description: string) => {
    const current = projection.settings.media_intelligence;
    const hasChange = Object.entries(next).some(([key, value]) => current[key as keyof typeof current] !== value);

    if (!hasChange) {
      return;
    }

    Object.entries(next).forEach(([key, value]) => {
      setNestedPatch(ensureMediaIntelligence(), key as keyof EventJourneyMediaIntelligencePatch, value);
    });
    applyDiff(diff, `media-${description}`, 'MediaIntelligence', description);
  };

  const setChannel = (
    key: 'whatsapp_direct' | 'whatsapp_groups' | 'public_upload' | 'telegram',
    next: boolean,
    label: string,
    available: boolean,
  ) => {
    const current = projection.intake_channels[key].enabled;

    if (!available && next) {
      skipDiff(diff, `skip-${key}`, label, `${label} nao entrou no template porque o pacote atual nao habilita esse canal.`);
      return;
    }

    if (current === next) {
      return;
    }

    payload.intake_channels ??= {};
    payload.intake_channels[key] = {
      enabled: next,
    } as NonNullable<EventJourneyUpdatePayload['intake_channels']>[typeof key];
    applyDiff(
      diff,
      `channel-${key}-${next ? 'on' : 'off'}`,
      label,
      next ? `Liga ${label.toLowerCase()} no rascunho local.` : `Desliga ${label.toLowerCase()} no rascunho local.`,
    );
  };

  const setWallModule = (next: boolean) => {
    const current = projection.settings.modules.wall;

    if (!wallAvailable && next) {
      skipDiff(diff, 'skip-wall', 'Telao', 'O template nao ativa o telao porque o pacote atual nao habilita esse destino.');
      return;
    }

    if (current === next) {
      return;
    }

    payload.modules ??= {};
    payload.modules.wall = next;
    applyDiff(
      diff,
      `wall-${next ? 'on' : 'off'}`,
      'Telao',
      next ? 'Liga o envio para o telao nas midias publicadas.' : 'Desliga o envio automatico para o telao.',
    );
  };

  switch (template.id) {
    case 'direct-approval':
      setModerationMode('none');
      setContentModeration({ enabled: false }, 'Desliga a analise de Safety neste fluxo.');
      setMediaIntelligence(
        { enabled: false, reply_text_enabled: false, reply_text_mode: 'disabled' },
        'Desliga contexto por IA e resposta automatica.',
      );
      break;

    case 'manual-review':
      setModerationMode('manual');
      setContentModeration({ enabled: false }, 'Desliga a analise de Safety porque a triagem fica humana.');
      setMediaIntelligence(
        { enabled: false, reply_text_enabled: false, reply_text_mode: 'disabled' },
        'Desliga VLM e resposta automatica para manter o fluxo mais controlado.',
      );
      break;

    case 'ai-moderating':
      setModerationMode('ai');
      setContentModeration(
        { enabled: true, mode: 'enforced', fallback_mode: 'review' },
        'Liga Safety em modo enforced com fallback para review.',
      );
      setMediaIntelligence(
        {
          enabled: true,
          mode: 'gate',
          fallback_mode: 'review',
          reply_text_enabled: true,
          reply_text_mode: 'ai',
        },
        'Liga VLM em modo gate e resposta automatica por IA.',
      );
      break;

    case 'hybrid-ai-human':
      setModerationMode('ai');
      setContentModeration(
        { enabled: true, mode: 'observe_only', fallback_mode: 'review' },
        'Liga Safety sem bloqueio automatico para priorizar revisao humana.',
      );
      setMediaIntelligence(
        {
          enabled: true,
          mode: 'enrich_only',
          fallback_mode: 'review',
          reply_text_enabled: true,
          reply_text_mode: 'ai',
        },
        'Liga VLM apenas para enriquecer o contexto e responder com IA.',
      );
      break;

    case 'social-simple':
      setModerationMode('ai');
      setChannel('whatsapp_direct', true, 'WhatsApp privado', whatsappDirectAvailable);
      setChannel('public_upload', true, 'Link de envio', uploadAvailable);
      setContentModeration(
        { enabled: true, mode: 'observe_only', fallback_mode: 'review' },
        'Liga Safety em modo leve para sinalizar review quando necessario.',
      );
      setMediaIntelligence(
        {
          enabled: true,
          mode: 'enrich_only',
          fallback_mode: 'review',
          reply_text_enabled: true,
          reply_text_mode: 'ai',
        },
        'Liga VLM leve com resposta automatica por IA.',
      );
      setWallModule(true);
      break;

    case 'corporate-controlled':
      setModerationMode('manual');
      setChannel('public_upload', false, 'Link de envio', uploadAvailable);
      setChannel('whatsapp_direct', false, 'WhatsApp privado', whatsappDirectAvailable);
      setChannel('whatsapp_groups', true, 'WhatsApp grupos', whatsappGroupsAvailable);
      setContentModeration(
        { enabled: true, mode: 'enforced', fallback_mode: 'review' },
        'Liga Safety em modo mais estrito para o fluxo corporativo.',
      );
      setMediaIntelligence(
        { enabled: false, reply_text_enabled: false, reply_text_mode: 'disabled' },
        'Desliga VLM e resposta automatica para reduzir variabilidade.',
      );
      setWallModule(false);
      break;
  }

  return { payload, diff };
}

export function buildJourneyTemplatePreview(
  projection: EventJourneyProjection,
  templateId: JourneyTemplateId,
): JourneyTemplatePreview {
  const template = JOURNEY_TEMPLATE_DEFINITIONS.find((item) => item.id === templateId);

  if (!template) {
    throw new Error(`Template de jornada desconhecido: ${templateId}`);
  }

  const { payload, diff } = buildTemplatePayload(projection, template);
  const previewProjection = applyJourneyPreviewPayload(projection, payload);

  return {
    template,
    payload,
    diff,
    previewProjection,
  };
}

export function mergeJourneyContentModerationSettings(
  settings: ApiEventContentModerationSettings,
  patch: EventJourneyContentModerationPatch | undefined,
): ApiEventContentModerationSettings {
  if (!patch) {
    return settings;
  }

  return {
    ...settings,
    ...patch,
    hard_block_thresholds: {
      ...settings.hard_block_thresholds,
      ...(patch.hard_block_thresholds ?? {}),
    },
    review_thresholds: {
      ...settings.review_thresholds,
      ...(patch.review_thresholds ?? {}),
    },
  };
}

export function mergeJourneyMediaIntelligenceSettings(
  settings: ApiEventMediaIntelligenceSettings,
  patch: EventJourneyMediaIntelligencePatch | undefined,
): ApiEventMediaIntelligenceSettings {
  if (!patch) {
    return settings;
  }

  return {
    ...settings,
    ...patch,
    reply_text_enabled: patch.reply_text_enabled ?? settings.reply_text_enabled,
    reply_text_mode: patch.reply_text_mode ?? settings.reply_text_mode,
    reply_fixed_templates: patch.reply_fixed_templates ?? settings.reply_fixed_templates,
    reply_prompt_preset_id: patch.reply_prompt_preset_id ?? settings.reply_prompt_preset_id,
    reply_prompt_override: patch.reply_prompt_override ?? settings.reply_prompt_override,
  };
}
