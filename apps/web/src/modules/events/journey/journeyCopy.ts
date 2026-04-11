import type { EventJourneyNode, EventJourneyNodeStatus, EventJourneyStageId } from './types';

interface JourneyNodeCopyOverride {
  label?: string;
  description?: string;
  summary?: string;
}

const JOURNEY_NODE_COPY_OVERRIDES: Record<string, JourneyNodeCopyOverride> = {
  entry_whatsapp_direct: {
    label: 'WhatsApp particular',
    description: 'Recebe fotos e videos enviados em conversa privada.',
    summary: 'Canal direto para convidados mandarem a midia sem depender de grupo.',
  },
  entry_whatsapp_groups: {
    label: 'Grupos de WhatsApp',
    description: 'Recebe midias enviadas em grupos ligados ao evento.',
    summary: 'Ideal quando a conversa do evento acontece em grupos ja organizados pela equipe.',
  },
  entry_telegram: {
    label: 'Telegram',
    description: 'Recebe fotos e videos pelo bot do evento.',
    summary: 'Usa o bot do Telegram para receber a midia e levar o envio para o fluxo do evento.',
  },
  entry_public_upload: {
    label: 'Link de envio',
    description: 'Recebe midias pelo link publico ou QR code do evento.',
    summary: 'Serve para convidado que prefere enviar por um link simples, sem precisar abrir conversa.',
  },
  entry_sender_blacklist: {
    label: 'Bloqueio de remetentes',
    description: 'Impede que contatos bloqueados continuem no fluxo.',
    summary: 'Filtra remetentes que nao devem mais enviar para o evento.',
  },
  processing_receive_feedback: {
    label: 'Aviso de recebimento',
    description: 'Mostra um retorno rapido quando a midia chega.',
    summary: 'Confirma para o convidado que o envio entrou no sistema.',
  },
  processing_download_media: {
    label: 'Registrar a midia',
    description: 'Guarda a foto ou video recebido no evento.',
    summary: 'Salva o envio para que ele possa seguir para analise e publicacao.',
  },
  processing_prepare_variants: {
    label: 'Preparar formatos da midia',
    description: 'Gera versoes leves para publicar com mais rapidez.',
    summary: 'Cria thumbs e formatos otimizados para galeria, telao e respostas.',
  },
  processing_safety_ai: {
    label: 'Analisar risco com IA',
    description: 'Usa IA para perceber sinais de risco antes da publicacao.',
  },
  processing_media_intelligence: {
    label: 'Entender contexto e legenda',
    description: 'Usa IA para entender a imagem, a legenda e o clima do envio.',
  },
  decision_event_moderation_mode: {
    label: 'Regra principal de aprovacao',
    description: 'Define se o evento aprova direto, usa IA ou pede revisao manual.',
  },
  decision_safety_result: {
    label: 'Resultado da analise de risco',
    description: 'Decide se a midia pode seguir, precisa de revisao ou deve parar.',
  },
  decision_context_gate: {
    label: 'Contexto da midia',
    description: 'Verifica se a imagem e o texto combinam com o evento.',
  },
  decision_media_type: {
    label: 'Foto ou video',
    description: 'Identifica o tipo da midia para aplicar a regra certa.',
  },
  decision_caption_presence: {
    label: 'Tem legenda?',
    description: 'Mostra se a midia chegou com texto ou legenda.',
  },
  output_reaction_final: {
    label: 'Confirmacao ao convidado',
    description: 'Envia uma reacao quando o canal de resposta estiver ligado.',
  },
  output_reply_text: {
    label: 'Mensagem automatica',
    description: 'Envia uma resposta fixa ou criada por IA.',
  },
  output_gallery: {
    label: 'Publicar na galeria',
    description: 'Disponibiliza a midia aprovada na galeria do evento.',
  },
  output_wall: {
    label: 'Mostrar no telao',
    description: 'Leva a midia aprovada para o telao do evento.',
  },
  output_print: {
    label: 'Enviar para impressao',
    description: 'Envia a midia para a trilha de impressao quando ela estiver ligada.',
  },
  output_silence: {
    label: 'Silenciar ou arquivar',
    description: 'Guarda a midia sem publicar quando o fluxo mandar parar.',
  },
};

const JOURNEY_TEXT_REPLACEMENTS: Array<[pattern: RegExp, replacement: string]> = [
  [/\bjourney-builder-v1\b/g, 'visao resumida'],
  [/\bReact Flow\b/g, 'mapa visual'],
  [/\bprojection\b/gi, 'configuracao atual'],
  [/\bbuilder\b/gi, 'tela'],
  [/\brenderer\b/gi, 'visualizacao'],
  [/\bpreview local\b/gi, 'pre-visualizacao desta tela'],
  [/\bVLM\b/g, 'IA de contexto'],
  [/\bMediaIntelligence\b/g, 'IA de contexto'],
  [/\bSafety\b/g, 'analise de risco'],
  [/\bfallback para review\b/gi, 'envio para revisao manual quando houver duvida'],
  [/\bgate\b/gi, 'filtro'],
  [/\bReview\b/g, 'Revisao manual'],
  [/\breview\b/g, 'revisao manual'],
];

export function humanizeJourneyText(text: string | null | undefined): string {
  if (!text) {
    return '';
  }

  return JOURNEY_TEXT_REPLACEMENTS.reduce(
    (current, [pattern, replacement]) => current.replace(pattern, replacement),
    text,
  );
}

export function getJourneyNodeCopy(node: Pick<EventJourneyNode, 'id' | 'label' | 'description' | 'summary'>) {
  const overrides = JOURNEY_NODE_COPY_OVERRIDES[node.id] ?? {};

  return {
    label: overrides.label ?? humanizeJourneyText(node.label),
    description: overrides.description ?? humanizeJourneyText(node.description),
    summary: overrides.summary ?? humanizeJourneyText(node.summary),
  };
}

export function humanizeJourneyStageLabel(stage: EventJourneyStageId) {
  switch (stage) {
    case 'entry':
      return 'Entrada';
    case 'processing':
      return 'Processamento';
    case 'decision':
      return 'Decisao';
    case 'output':
    default:
      return 'Saida';
  }
}

export function describeJourneyStage(stage: EventJourneyStageId) {
  switch (stage) {
    case 'entry':
      return 'Mostra por onde a foto ou o video chegam ao evento.';
    case 'processing':
      return 'Mostra o que o sistema faz logo depois que a midia chega.';
    case 'decision':
      return 'Mostra as regras que podem mudar o caminho da midia.';
    case 'output':
    default:
      return 'Mostra o que acontece com a midia no final do fluxo.';
  }
}

export function humanizeJourneyStatusLabel(status: EventJourneyNodeStatus) {
  switch (status) {
    case 'active':
      return 'Ligado';
    case 'inactive':
      return 'Desligado';
    case 'locked':
      return 'Disponivel em outro plano';
    case 'required':
      return 'Sempre acontece';
    case 'unavailable':
    default:
      return 'Indisponivel agora';
  }
}

export function describeJourneyStatus(status: EventJourneyNodeStatus) {
  switch (status) {
    case 'active':
      return 'Esta etapa esta ligada e participa do fluxo atual.';
    case 'inactive':
      return 'Esta etapa existe, mas esta desligada neste evento.';
    case 'locked':
      return 'Esta etapa depende de recurso que nao esta liberado no plano atual.';
    case 'required':
      return 'Esta etapa faz parte do caminho padrao e sempre acontece.';
    case 'unavailable':
    default:
      return 'Esta etapa nao pode ser usada neste momento.';
  }
}

export function humanizeJourneyEditability(editable: boolean) {
  return editable ? 'Pode ajustar' : 'Feito pelo sistema';
}

export function describeJourneyEditability(editable: boolean) {
  return editable
    ? 'Voce pode ajustar esta etapa por aqui.'
    : 'Esta etapa roda automaticamente e nao depende de configuracao manual nesta tela.';
}

export function humanizeJourneyBranchLabel(branchId: string, label: string) {
  const normalizedLabel = humanizeJourneyText(label).trim();

  if (
    branchId === 'default'
    || ['padrao', 'opcional', 'obrigatorio', 'automatico', 'permitido'].includes(normalizedLabel.toLowerCase())
  ) {
    return null;
  }

  switch (normalizedLabel.toLowerCase()) {
    case 'safe':
    case 'seguro':
      return 'Tudo certo';
    case 'revisao':
    case 'revisao manual':
      return 'Revisao manual';
    case 'blocked':
    case 'bloqueado':
      return 'Bloqueia';
    case 'approved':
    case 'aprovado':
      return 'Aprova';
    case 'with_caption':
    case 'com legenda':
      return 'Com legenda';
    case 'without_caption':
    case 'sem legenda':
      return 'Sem legenda';
    case 'photo':
    case 'foto':
      return 'Foto';
    case 'video':
      return 'Video';
    default:
      return normalizedLabel;
  }
}
