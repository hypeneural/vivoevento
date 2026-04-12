import type {
  EventOperationsStationKey,
  EventOperationsVisualRole,
} from '@eventovivo/shared-types/event-operations';

import type { EventOperationsV0Room } from '../types';

export interface EventOperationsVisualRoleDefinition {
  role: EventOperationsVisualRole;
  label: string;
  description: string;
  default_anchor_station_key: EventOperationsStationKey;
  support_station_keys: EventOperationsStationKey[];
}

export interface EventOperationsStationVisualGuide {
  station_key: EventOperationsStationKey;
  full_motion_label: string;
  reduced_motion_label: string;
}

export interface EventOperationsResolvedVisualRole extends EventOperationsVisualRoleDefinition {
  anchor_station_key: EventOperationsStationKey;
  mood: 'calm' | 'busy' | 'warning' | 'critical';
  summary: string;
}

export const EVENT_OPERATIONS_VISUAL_ROLE_CONFIG: Record<
  EventOperationsVisualRole,
  EventOperationsVisualRoleDefinition
> = {
  coordinator: {
    role: 'coordinator',
    label: 'Coordinator',
    description: 'Sintetiza saude global e promove a estacao dominante.',
    default_anchor_station_key: 'gallery',
    support_station_keys: ['alerts', 'gallery', 'wall'],
  },
  dispatcher: {
    role: 'dispatcher',
    label: 'Dispatcher',
    description: 'Recebe entrada e encaminha a recepcao para o restante da sala.',
    default_anchor_station_key: 'intake',
    support_station_keys: ['download', 'variants'],
  },
  runner: {
    role: 'runner',
    label: 'Runner',
    description: 'Leva cards simbolicos entre etapas e reforca mudanca de fase.',
    default_anchor_station_key: 'download',
    support_station_keys: ['variants', 'gallery', 'feedback'],
  },
  reviewer: {
    role: 'reviewer',
    label: 'Reviewer',
    description: 'Fica associado ao backlog humano e as decisoes pendentes.',
    default_anchor_station_key: 'human_review',
    support_station_keys: ['gallery'],
  },
  operator: {
    role: 'operator',
    label: 'Operator',
    description: 'Reage ao health do wall e ao current/next.',
    default_anchor_station_key: 'wall',
    support_station_keys: ['alerts'],
  },
  triage: {
    role: 'triage',
    label: 'Triage',
    description: 'Expressa safety e IA como avaliacao operacional.',
    default_anchor_station_key: 'safety',
    support_station_keys: ['intelligence'],
  },
};

export const EVENT_OPERATIONS_STATION_VISUAL_GUIDE: Record<
  EventOperationsStationKey,
  EventOperationsStationVisualGuide
> = {
  intake: {
    station_key: 'intake',
    full_motion_label: 'pulsos curtos e fila simbolica',
    reduced_motion_label: 'contagem e pulso discreto',
  },
  download: {
    station_key: 'download',
    full_motion_label: 'esteira curta com caixa de entrada',
    reduced_motion_label: 'indicador de entrada e saida',
  },
  variants: {
    station_key: 'variants',
    full_motion_label: 'bancada com thumbs nascendo',
    reduced_motion_label: 'contador de variantes e troca de estado',
  },
  safety: {
    station_key: 'safety',
    full_motion_label: 'scanner com luz amarela e vermelha',
    reduced_motion_label: 'mudanca de cor por severidade',
  },
  intelligence: {
    station_key: 'intelligence',
    full_motion_label: 'mesa de leitura e terminal contextual',
    reduced_motion_label: 'badge de leitura e contexto',
  },
  human_review: {
    station_key: 'human_review',
    full_motion_label: 'pilha fisica de cards e lampada de warning',
    reduced_motion_label: 'pilha estatica e contador visivel',
  },
  gallery: {
    station_key: 'gallery',
    full_motion_label: 'parede viva com thumbs recentes',
    reduced_motion_label: 'moldura de recentes e selo estavel',
  },
  wall: {
    station_key: 'wall',
    full_motion_label: 'monitor central com current/next e monitor central ativo',
    reduced_motion_label: 'selo claro de current/next/health',
  },
  feedback: {
    station_key: 'feedback',
    full_motion_label: 'mensagens saindo da estacao',
    reduced_motion_label: 'contador de mensagens e reacoes',
  },
  alerts: {
    station_key: 'alerts',
    full_motion_label: 'sirene discreta, nunca permanente',
    reduced_motion_label: 'badge de alerta estatico',
  },
};

function resolveHealthMood(status: EventOperationsV0Room['health']['status']): EventOperationsResolvedVisualRole['mood'] {
  if (status === 'risk' || status === 'offline') {
    return 'critical';
  }

  if (status === 'attention') {
    return 'warning';
  }

  return 'calm';
}

function getStation(room: EventOperationsV0Room, stationKey: EventOperationsStationKey) {
  return room.stations.find((station) => station.station_key === stationKey) ?? null;
}

export function resolveVisualRoleAssignments(room: EventOperationsV0Room): EventOperationsResolvedVisualRole[] {
  const intake = getStation(room, 'intake');
  const humanReview = getStation(room, 'human_review');
  const wall = getStation(room, 'wall');
  const safety = getStation(room, 'safety');
  const intelligence = getStation(room, 'intelligence');
  const dominantStationKey = room.health.dominant_station_key;

  return [
    {
      ...EVENT_OPERATIONS_VISUAL_ROLE_CONFIG.coordinator,
      anchor_station_key: dominantStationKey ?? 'gallery',
      mood: resolveHealthMood(room.health.status),
      summary: room.health.summary,
    },
    {
      ...EVENT_OPERATIONS_VISUAL_ROLE_CONFIG.dispatcher,
      anchor_station_key: 'intake',
      mood: (intake?.throughput_per_minute ?? 0) >= 6 ? 'busy' : 'calm',
      summary: intake?.throughput_per_minute
        ? `${intake.throughput_per_minute} entrada(s) por minuto.`
        : 'Recepcao em ritmo leve.',
    },
    {
      ...EVENT_OPERATIONS_VISUAL_ROLE_CONFIG.runner,
      anchor_station_key: room.counters.backlog_total > 0 ? dominantStationKey ?? 'download' : 'gallery',
      mood: room.counters.backlog_total > 0 ? 'busy' : 'calm',
      summary: room.counters.backlog_total > 0
        ? `${room.counters.backlog_total} item(ns) ainda atravessam o fluxo.`
        : 'Fluxo curto entre entrada e publicacao.',
    },
    {
      ...EVENT_OPERATIONS_VISUAL_ROLE_CONFIG.reviewer,
      anchor_station_key: 'human_review',
      mood: (humanReview?.queue_depth ?? 0) > 0 ? 'warning' : 'calm',
      summary: (humanReview?.queue_depth ?? 0) > 0
        ? `${humanReview?.queue_depth ?? 0} item(ns) aguardam decisao humana.`
        : 'Mesa humana sem fila relevante.',
    },
    {
      ...EVENT_OPERATIONS_VISUAL_ROLE_CONFIG.operator,
      anchor_station_key: 'wall',
      mood:
        wall?.health === 'risk' || wall?.health === 'offline'
          ? 'critical'
          : wall?.health === 'attention'
            ? 'warning'
            : 'calm',
      summary:
        wall?.health === 'risk' || wall?.health === 'offline'
          ? 'Wall exige acao imediata.'
          : wall?.health === 'attention'
            ? 'Wall sob atencao.'
            : 'Wall estavel.',
    },
    {
      ...EVENT_OPERATIONS_VISUAL_ROLE_CONFIG.triage,
      anchor_station_key: safety?.queue_depth && safety.queue_depth >= (intelligence?.queue_depth ?? 0)
        ? 'safety'
        : 'intelligence',
      mood:
        safety?.health === 'attention' || intelligence?.health === 'attention'
          ? 'warning'
          : 'calm',
      summary:
        safety?.health === 'attention' || intelligence?.health === 'attention'
          ? 'Safety e IA contextual pedem leitura cuidadosa.'
          : 'Triage automatica sem ruido dominante.',
    },
  ];
}
