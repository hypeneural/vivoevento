import type { JourneyGraph, JourneyGraphEdge, JourneyGraphNode } from './buildJourneyGraph';
import type {
  EventJourneyBuiltScenario,
  EventJourneyProjection,
  EventJourneySimulationOutcome,
} from './types';

type JourneyScenarioChannel = 'whatsapp_direct' | 'whatsapp_groups' | 'telegram' | 'public_upload';
type JourneyScenarioMediaType = 'photo' | 'video';
type JourneyScenarioOutcomeHint = 'approved' | 'review' | 'blocked' | 'default';

interface JourneyScenarioInput {
  channel?: JourneyScenarioChannel;
  mediaType?: JourneyScenarioMediaType;
  hasCaption?: boolean;
  senderBlocked?: boolean;
  safetyOutcome?: Exclude<JourneyScenarioOutcomeHint, 'default'>;
  contextOutcome?: JourneyScenarioOutcomeHint;
  includeReplyOutputs?: boolean;
  includeWallOutput?: boolean;
}

interface JourneyScenarioBlueprint {
  id: string;
  label: string;
  description: string;
  input: JourneyScenarioInput;
}

const CHANNEL_NODE_IDS: Record<JourneyScenarioChannel, string> = {
  whatsapp_direct: 'entry_whatsapp_direct',
  whatsapp_groups: 'entry_whatsapp_groups',
  telegram: 'entry_telegram',
  public_upload: 'entry_public_upload',
};

const CHANNEL_LABELS: Record<JourneyScenarioChannel, string> = {
  whatsapp_direct: 'WhatsApp privado',
  whatsapp_groups: 'WhatsApp grupos',
  telegram: 'Telegram',
  public_upload: 'link de envio',
};

const REPLY_CAPABLE_CHANNELS = new Set<JourneyScenarioChannel>([
  'whatsapp_direct',
  'whatsapp_groups',
  'telegram',
]);

const SCENARIO_BLUEPRINTS: JourneyScenarioBlueprint[] = [
  {
    id: 'photo_whatsapp_private_with_caption',
    label: 'Foto com legenda',
    description: 'Simula uma foto recebida pelo WhatsApp privado com legenda.',
    input: {
      channel: 'whatsapp_direct',
      mediaType: 'photo',
      hasCaption: true,
    },
  },
  {
    id: 'photo_whatsapp_group_without_caption',
    label: 'Foto sem legenda por grupo',
    description: 'Simula uma foto recebida por grupo de WhatsApp sem legenda.',
    input: {
      channel: 'whatsapp_groups',
      mediaType: 'photo',
      hasCaption: false,
    },
  },
  {
    id: 'video_telegram',
    label: 'Video pelo Telegram',
    description: 'Simula um video recebido pelo Telegram.',
    input: {
      channel: 'telegram',
      mediaType: 'video',
      hasCaption: false,
    },
  },
  {
    id: 'blocked_sender',
    label: 'Remetente bloqueado',
    description: 'Simula uma origem bloqueada antes do processamento.',
    input: {
      channel: 'whatsapp_direct',
      senderBlocked: true,
    },
  },
  {
    id: 'safety_blocked',
    label: 'Safety bloqueou',
    description: 'Simula um bloqueio causado pela etapa de Safety.',
    input: {
      safetyOutcome: 'blocked',
    },
  },
  {
    id: 'safety_review',
    label: 'Safety pediu revisao',
    description: 'Simula um envio para revisao causado pelo Safety.',
    input: {
      safetyOutcome: 'review',
    },
  },
  {
    id: 'vlm_gate_review',
    label: 'VLM gate pediu revisao',
    description: 'Simula uma revisao disparada pelo gate de contexto.',
    input: {
      contextOutcome: 'review',
    },
  },
  {
    id: 'approved_and_published',
    label: 'Aprovado e publicado',
    description: 'Simula um caminho bem-sucedido ate a publicacao.',
    input: {
      mediaType: 'photo',
      hasCaption: true,
      contextOutcome: 'default',
      includeReplyOutputs: true,
      includeWallOutput: true,
    },
  },
  {
    id: 'rejected_with_reply',
    label: 'Rejeitado com resposta',
    description: 'Simula um bloqueio com mensagem final automatica.',
    input: {
      safetyOutcome: 'blocked',
      includeReplyOutputs: true,
    },
  },
];

interface SimulationState {
  nodeIds: string[];
  edgeIds: string[];
  outcome: EventJourneySimulationOutcome;
  channel: JourneyScenarioChannel;
}

function uniqueIds(ids: string[]) {
  return Array.from(new Set(ids));
}

function findNode(graph: JourneyGraph, nodeId: string) {
  return graph.nodes.find((node) => node.id === nodeId);
}

function findEdge(
  graph: JourneyGraph,
  sourceNodeId: string,
  branchId: string,
  targetNodeId?: string,
) {
  return graph.edges.find((edge) =>
    edge.source === sourceNodeId
    && edge.sourceHandle === `branch:${branchId}`
    && (targetNodeId ? edge.target === targetNodeId : true),
  );
}

function isOperationalNode(node?: JourneyGraphNode) {
  if (!node) {
    return false;
  }

  return node.status === 'active' || node.status === 'required';
}

function isActiveEdge(edge?: JourneyGraphEdge) {
  if (!edge) {
    return false;
  }

  return edge.data.branch.active;
}

function resolveChannel(
  graph: JourneyGraph,
  preferred?: JourneyScenarioChannel,
): JourneyScenarioChannel {
  if (preferred) {
    return preferred;
  }

  const orderedChannels: JourneyScenarioChannel[] = [
    'whatsapp_direct',
    'whatsapp_groups',
    'telegram',
    'public_upload',
  ];

  return orderedChannels.find((channel) => isOperationalNode(findNode(graph, CHANNEL_NODE_IDS[channel])))
    ?? 'whatsapp_direct';
}

function followEdge(
  graph: JourneyGraph,
  state: SimulationState,
  sourceNodeId: string,
  branchId: string,
  fallbackTargetNodeId?: string,
) {
  const edge = findEdge(graph, sourceNodeId, branchId, fallbackTargetNodeId);

  state.nodeIds.push(sourceNodeId);

  if (!edge) {
    if (fallbackTargetNodeId) {
      state.nodeIds.push(fallbackTargetNodeId);
    }

    return fallbackTargetNodeId ?? null;
  }

  state.edgeIds.push(edge.id);
  state.nodeIds.push(edge.target);

  return edge.target;
}

function pushUniqueNode(state: SimulationState, nodeId: string) {
  state.nodeIds.push(nodeId);
}

function replyOutputsForChannel(channel: JourneyScenarioChannel) {
  return REPLY_CAPABLE_CHANNELS.has(channel);
}

function simulationAvailabilityReason(
  graph: JourneyGraph,
  scenarioId: string,
  channel: JourneyScenarioChannel,
) {
  const channelNode = findNode(graph, CHANNEL_NODE_IDS[channel]);
  const safetyNode = findNode(graph, 'decision_safety_result');
  const contextNode = findNode(graph, 'decision_context_gate');
  const blacklistNode = findNode(graph, 'entry_sender_blacklist');
  const replyNode = findNode(graph, 'output_reply_text');

  switch (scenarioId) {
    case 'photo_whatsapp_private_with_caption':
    case 'photo_whatsapp_group_without_caption':
    case 'video_telegram':
      return isOperationalNode(channelNode)
        ? null
        : `${CHANNEL_LABELS[channel]} esta desligado na jornada atual.`;
    case 'blocked_sender':
      return isOperationalNode(blacklistNode)
        ? null
        : 'O bloqueio de remetentes nao esta ativo na jornada atual.';
    case 'safety_blocked':
    case 'safety_review':
      return isOperationalNode(safetyNode)
        ? null
        : 'Safety por IA nao esta ativo na jornada atual.';
    case 'vlm_gate_review':
      return isOperationalNode(contextNode)
        ? null
        : 'O gate de contexto nao esta ativo na jornada atual.';
    case 'rejected_with_reply':
      if (!isOperationalNode(safetyNode) && !isOperationalNode(contextNode)) {
        return 'Nao existe uma trilha ativa de bloqueio por IA na jornada atual.';
      }

      if (!replyOutputsForChannel(channel)) {
        return 'O canal escolhido nao suporta resposta automatica na jornada atual.';
      }

      return isOperationalNode(replyNode)
        ? null
        : 'A resposta automatica esta desligada na jornada atual.';
    default:
      return null;
  }
}

function basePrefix(input: JourneyScenarioInput, channel: JourneyScenarioChannel) {
  if (input.senderBlocked) {
    return 'Neste cenario, o remetente ja esta bloqueado.';
  }

  const mediaLabel = input.mediaType === 'video' ? 'um video' : 'uma foto';
  const captionLabel = input.hasCaption === false ? 'sem legenda' : 'com legenda';

  return `Neste cenario, ${mediaLabel} chega por ${CHANNEL_LABELS[channel]} ${captionLabel}.`;
}

function outputsClause(
  graph: JourneyGraph,
  nodeIds: string[],
  channel: JourneyScenarioChannel,
) {
  const gallery = nodeIds.includes('output_gallery');
  const wall = nodeIds.includes('output_wall') && isOperationalNode(findNode(graph, 'output_wall'));
  const reply = nodeIds.includes('output_reply_text') && replyOutputsForChannel(channel)
    && isOperationalNode(findNode(graph, 'output_reply_text'));

  if (gallery && wall && reply) {
    return 'publica na galeria e no telao com resposta automatica.';
  }

  if (gallery && wall) {
    return 'publica na galeria e no telao.';
  }

  if (gallery && reply) {
    return 'publica na galeria com resposta automatica.';
  }

  if (gallery) {
    return 'publica na galeria.';
  }

  if (reply) {
    return 'encerra com mensagem automatica.';
  }

  return 'encerra sem destinos visiveis.';
}

function buildHumanText(
  graph: JourneyGraph,
  input: JourneyScenarioInput,
  state: SimulationState,
) {
  const prefix = basePrefix(input, state.channel);

  if (input.senderBlocked) {
    const suffix = state.nodeIds.includes('output_reply_text')
      ? 'A jornada interrompe o fluxo antes do processamento, envia a midia para silencio e dispara uma mensagem automatica.'
      : 'A jornada interrompe o fluxo antes do processamento e envia a midia para silencio.';

    return `${prefix} ${suffix}`;
  }

  if (state.outcome === 'blocked') {
    const suffix = state.nodeIds.includes('output_reply_text')
      ? 'A jornada bloqueia a midia, envia para silencio e dispara uma mensagem automatica.'
      : 'A jornada bloqueia a midia e envia para silencio.';

    return `${prefix} ${suffix}`;
  }

  if (state.outcome === 'review') {
    return `${prefix} A jornada envia a midia para revisao manual antes da publicacao.`;
  }

  return `${prefix} A jornada aprova a midia e ${outputsClause(graph, state.nodeIds, state.channel)}`;
}

function simulateFromBlueprint(
  projection: EventJourneyProjection,
  graph: JourneyGraph,
  blueprint: JourneyScenarioBlueprint,
): EventJourneyBuiltScenario {
  const mergedPreset = projection.simulation_presets.find((preset) => preset.id === blueprint.id);
  const input = {
    ...blueprint.input,
    ...(mergedPreset?.input ?? {}),
  } as JourneyScenarioInput;
  const channel = resolveChannel(graph, input.channel);
  const unavailableReason = simulationAvailabilityReason(graph, blueprint.id, channel);
  const state: SimulationState = {
    nodeIds: [],
    edgeIds: [],
    outcome: 'inactive',
    channel,
  };

  const entryNodeId = CHANNEL_NODE_IDS[channel];
  pushUniqueNode(state, entryNodeId);

  if (input.senderBlocked) {
    pushUniqueNode(state, 'entry_sender_blacklist');
    followEdge(graph, state, 'entry_sender_blacklist', 'blocked', 'output_silence');

    state.outcome = 'blocked';
  } else {
    followEdge(graph, state, entryNodeId, 'default', 'processing_receive_feedback');
    followEdge(graph, state, 'processing_receive_feedback', 'default', 'processing_download_media');
    followEdge(graph, state, 'processing_download_media', 'default', 'processing_prepare_variants');
    followEdge(graph, state, 'processing_prepare_variants', 'default', 'decision_event_moderation_mode');

    const moderationMode = projection.settings.moderation_mode ?? 'manual';

    if (moderationMode === 'none') {
      followEdge(graph, state, 'decision_event_moderation_mode', 'approved', 'output_gallery');
      state.outcome = 'approved';
    } else if (moderationMode === 'manual') {
      followEdge(graph, state, 'decision_event_moderation_mode', 'review', 'output_gallery');
      state.outcome = 'review';
    } else {
      const moderationTarget = followEdge(
        graph,
        state,
        'decision_event_moderation_mode',
        'default',
        'decision_safety_result',
      );

      const safetyBranch = input.safetyOutcome
        ?? (isOperationalNode(findNode(graph, 'decision_safety_result')) ? 'safe' : 'default');
      const safetyTarget = followEdge(
        graph,
        state,
        moderationTarget ?? 'decision_safety_result',
        safetyBranch,
        safetyBranch === 'blocked'
          ? 'output_silence'
          : safetyBranch === 'review'
            ? 'output_gallery'
            : 'decision_context_gate',
      );

      if (safetyBranch === 'blocked') {
        state.outcome = 'blocked';
      } else if (safetyBranch === 'review') {
        state.outcome = 'review';
      } else {
        const contextBranch = input.contextOutcome
          ?? (isOperationalNode(findNode(graph, 'decision_context_gate')) ? 'default' : 'default');
        const contextTarget = followEdge(
          graph,
          state,
          safetyTarget ?? 'decision_context_gate',
          contextBranch,
          contextBranch === 'blocked'
            ? 'output_silence'
            : contextBranch === 'review' || contextBranch === 'approved'
              ? 'output_gallery'
              : 'decision_media_type',
        );

        if (contextBranch === 'blocked') {
          state.outcome = 'blocked';
        } else if (contextBranch === 'review') {
          state.outcome = 'review';
        } else if (contextBranch === 'approved') {
          state.outcome = 'approved';
        } else {
          const mediaBranch = input.mediaType === 'video' ? 'video' : 'photo';
          followEdge(graph, state, contextTarget ?? 'decision_media_type', mediaBranch, 'decision_caption_presence');
          const captionBranch = input.hasCaption === false ? 'without_caption' : 'with_caption';
          followEdge(graph, state, 'decision_caption_presence', captionBranch, 'output_gallery');
          state.outcome = 'approved';
        }
      }
    }
  }

  if (state.outcome === 'approved' && input.includeWallOutput && projection.settings.destinations.wall) {
    pushUniqueNode(state, 'output_wall');
  }

  if (
    input.includeReplyOutputs
    && replyOutputsForChannel(channel)
    && isOperationalNode(findNode(graph, 'output_reply_text'))
  ) {
    pushUniqueNode(state, 'output_reply_text');
    pushUniqueNode(state, 'output_reaction_final');
  }

  state.nodeIds = uniqueIds(state.nodeIds);
  state.edgeIds = uniqueIds(state.edgeIds);

  return {
    id: blueprint.id,
    label: mergedPreset?.label ?? blueprint.label,
    description: mergedPreset?.description ?? blueprint.description,
    input: (mergedPreset?.input as Record<string, unknown> | undefined) ?? (input as Record<string, unknown>),
    available: unavailableReason === null,
    unavailableReason,
    highlightedNodeIds: state.nodeIds,
    highlightedEdgeIds: state.edgeIds,
    humanText: buildHumanText(graph, input, state),
    outcome: state.outcome,
  };
}

export function simulateJourneyScenario(
  projection: EventJourneyProjection,
  graph: JourneyGraph,
  scenarioId: string,
) {
  const blueprint = SCENARIO_BLUEPRINTS.find((candidate) => candidate.id === scenarioId);

  if (!blueprint) {
    throw new Error(`Journey scenario [${scenarioId}] was not found.`);
  }

  return simulateFromBlueprint(projection, graph, blueprint);
}

export function buildJourneyScenarios(
  projection: EventJourneyProjection,
  graph: JourneyGraph,
) {
  return SCENARIO_BLUEPRINTS.map((blueprint) => simulateFromBlueprint(projection, graph, blueprint));
}
