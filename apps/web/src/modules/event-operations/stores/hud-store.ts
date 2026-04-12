import type { EventOperationsConnectionSummary, EventOperationsHealthStatus, EventOperationsWallSummary } from '@eventovivo/shared-types/event-operations';

import type { EventOperationsV0Room } from '../types';

export interface EventOperationsHudState {
  event_title: string;
  global_status_label: string;
  global_summary: string;
  server_clock_label: string;
  connection_label: string;
  connection_tone: 'healthy' | 'attention' | 'critical' | 'neutral';
  wall_label: string;
  wall_tone: 'healthy' | 'attention' | 'critical' | 'neutral';
  human_queue_label: string;
  human_queue_tone: 'healthy' | 'attention' | 'critical' | 'neutral';
}

function formatServerClock(value: string): string {
  try {
    return new Intl.DateTimeFormat('pt-BR', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    }).format(new Date(value));
  } catch {
    return value;
  }
}

function mapHealthTone(status: EventOperationsHealthStatus): EventOperationsHudState['wall_tone'] {
  switch (status) {
    case 'healthy':
      return 'healthy';
    case 'attention':
      return 'attention';
    case 'risk':
    case 'offline':
      return 'critical';
    default:
      return 'neutral';
  }
}

function formatConnectionLabel(connection: EventOperationsConnectionSummary): string {
  if (!connection.realtime_connected) {
    return 'Polling read-only';
  }

  switch (connection.status) {
    case 'connected':
      return 'Conectado';
    case 'connecting':
      return 'Reconectando...';
    case 'resyncing':
      return 'Sincronizando a sala...';
    case 'degraded':
      return 'Sala degradada';
    case 'offline':
      return 'Offline';
    default:
      return connection.status;
  }
}

function formatWallLabel(wall: EventOperationsWallSummary): string {
  return `${wall.online_players} online / ${wall.degraded_players} degradado / ${wall.offline_players} offline`;
}

export function buildOperationsHudState(room: EventOperationsV0Room): EventOperationsHudState {
  return {
    event_title: room.event.title,
    global_status_label: room.health.status === 'healthy'
      ? 'Saudavel'
      : room.health.status === 'attention'
        ? 'Em atencao'
        : 'Em risco',
    global_summary: room.health.summary,
    server_clock_label: formatServerClock(room.server_time),
    connection_label: formatConnectionLabel(room.connection),
    connection_tone: !room.connection.realtime_connected ? 'attention' : mapHealthTone(room.health.status),
    wall_label: formatWallLabel(room.wall),
    wall_tone: mapHealthTone(room.wall.health),
    human_queue_label: `${room.counters.human_review_pending} pendente(s)`,
    human_queue_tone: room.counters.human_review_pending > 0 ? 'attention' : 'healthy',
  };
}
