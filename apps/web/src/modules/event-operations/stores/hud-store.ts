import { useSyncExternalStore } from 'react';

import type {
  EventOperationsConnectionSummary,
  EventOperationsHealthStatus,
  EventOperationsRoomSnapshot,
  EventOperationsWallSummary,
} from '@eventovivo/shared-types/event-operations';

type Listener = () => void;

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

export interface EventOperationsHudStoreSnapshot {
  room_snapshot_version: number | null;
  hud: EventOperationsHudState | null;
}

const EMPTY_HUD_SNAPSHOT: EventOperationsHudStoreSnapshot = {
  room_snapshot_version: null,
  hud: null,
};

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
  switch (connection.status) {
    case 'connected':
      return connection.realtime_connected ? 'Conectado' : 'Polling read-only';
    case 'connecting':
      return 'Reconectando...';
    case 'resyncing':
      return 'Sincronizando a sala...';
    case 'degraded':
      return 'Sala degradada: dados ao vivo indisponiveis';
    case 'offline':
      return 'Offline';
    default:
      return connection.realtime_connected ? connection.status : 'Polling read-only';
  }
}

function formatWallLabel(wall: EventOperationsWallSummary): string {
  return `${wall.online_players} online / ${wall.degraded_players} degradado / ${wall.offline_players} offline`;
}

export function buildOperationsHudState(room: EventOperationsRoomSnapshot): EventOperationsHudState {
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

class EventOperationsHudStore {
  private snapshot: EventOperationsHudStoreSnapshot = EMPTY_HUD_SNAPSHOT;

  private readonly listeners = new Set<Listener>();

  subscribe = (listener: Listener) => {
    this.listeners.add(listener);

    return () => {
      this.listeners.delete(listener);
    };
  };

  getSnapshot = () => this.snapshot;

  getServerSnapshot = () => this.snapshot;

  setRoom(room: EventOperationsRoomSnapshot) {
    this.snapshot = {
      room_snapshot_version: room.snapshot_version,
      hud: buildOperationsHudState(room),
    };

    this.emit();
  }

  reset() {
    this.snapshot = EMPTY_HUD_SNAPSHOT;
    this.emit();
  }

  private emit() {
    this.listeners.forEach((listener) => listener());
  }
}

export const eventOperationsHudStore = new EventOperationsHudStore();

export function useEventOperationsHudSnapshot() {
  return useSyncExternalStore(
    eventOperationsHudStore.subscribe,
    eventOperationsHudStore.getSnapshot,
    eventOperationsHudStore.getServerSnapshot,
  );
}
