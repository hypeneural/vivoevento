import {
  EVENT_OPERATIONS_SCHEMA_VERSION,
  type EventOperationsRoomSnapshot,
  type EventOperationsStationKey,
  type EventOperationsStationState,
} from '@eventovivo/shared-types/event-operations';

export {
  eventOperationsAlertDeltaFixture,
  eventOperationsGapDeltaFixture,
} from './operations-deltas.fixture';

const serverTime = '2026-04-11T18:42:15Z';

const stationLabels: Record<EventOperationsStationKey, string> = {
  intake: 'Recepcao',
  download: 'Download / Arquivo',
  variants: 'Laboratorio / Variantes',
  safety: 'Safety AI',
  intelligence: 'IA de contexto',
  human_review: 'Moderacao humana',
  gallery: 'Galeria',
  wall: 'Telao',
  feedback: 'Feedback',
  alerts: 'Alertas',
};

const stationGroups: Record<EventOperationsStationKey, EventOperationsStationState['render_group']> = {
  intake: 'intake',
  download: 'processing',
  variants: 'processing',
  safety: 'review',
  intelligence: 'review',
  human_review: 'review',
  gallery: 'publishing',
  wall: 'wall',
  feedback: 'publishing',
  alerts: 'system',
};

function createStation(
  stationKey: EventOperationsStationKey,
  overrides: Partial<EventOperationsStationState> = {},
): EventOperationsStationState {
  return {
    station_key: stationKey,
    label: stationLabels[stationKey],
    health: 'healthy',
    backlog_count: 0,
    queue_depth: 0,
    station_load: 0.1,
    throughput_per_minute: 0,
    recent_items: [],
    animation_hint: 'none',
    render_group: stationGroups[stationKey],
    updated_at: serverTime,
    ...overrides,
  };
}

const healthyStations = [
  createStation('intake', {
    throughput_per_minute: 8,
    animation_hint: 'intake_pulse',
  }),
  createStation('download'),
  createStation('variants'),
  createStation('safety'),
  createStation('intelligence'),
  createStation('human_review'),
  createStation('gallery', {
    throughput_per_minute: 5,
    animation_hint: 'gallery_publish',
  }),
  createStation('wall', {
    throughput_per_minute: 2,
    animation_hint: 'wall_health',
  }),
  createStation('feedback'),
  createStation('alerts'),
];

export const eventOperationsHealthySnapshotFixture: EventOperationsRoomSnapshot = {
  schema_version: EVENT_OPERATIONS_SCHEMA_VERSION,
  snapshot_version: 1,
  timeline_cursor: 'evt_000100',
  event_sequence: 100,
  server_time: serverTime,
  event: {
    id: 42,
    title: 'Casamento Ana e Bruno',
    slug: 'casamento-ana-bruno',
    status: 'live',
    timezone: 'America/Sao_Paulo',
  },
  health: {
    status: 'healthy',
    dominant_station_key: null,
    summary: 'Operacao saudavel',
    updated_at: serverTime,
  },
  connection: {
    status: 'connected',
    realtime_connected: true,
    last_connected_at: serverTime,
    last_resync_at: serverTime,
  },
  counters: {
    backlog_total: 0,
    human_review_pending: 0,
    processing_failures: 0,
    intake_per_minute: 8,
    published_gallery_total: 128,
    published_wall_total: 32,
  },
  stations: healthyStations,
  alerts: [],
  wall: {
    health: 'healthy',
    online_players: 2,
    degraded_players: 0,
    offline_players: 0,
    current_item_id: 'media_120',
    next_item_id: 'media_121',
    confidence: 'high',
  },
  timeline: [
    {
      id: 'evt_000100',
      event_sequence: 100,
      station_key: 'gallery',
      event_key: 'media.published.gallery',
      severity: 'info',
      urgency: 'normal',
      title: 'Midia publicada',
      summary: 'Uma midia recente entrou na galeria.',
      occurred_at: serverTime,
      render_group: 'publishing',
      animation_hint: 'gallery_publish',
    },
  ],
};

export const eventOperationsHumanReviewBottleneckSnapshotFixture: EventOperationsRoomSnapshot = {
  ...eventOperationsHealthySnapshotFixture,
  snapshot_version: 2,
  timeline_cursor: 'evt_000140',
  event_sequence: 140,
  health: {
    status: 'attention',
    dominant_station_key: 'human_review',
    summary: 'Fila humana crescendo',
    updated_at: serverTime,
  },
  counters: {
    ...eventOperationsHealthySnapshotFixture.counters,
    backlog_total: 18,
    human_review_pending: 12,
  },
  stations: healthyStations.map((station) =>
    station.station_key === 'human_review'
      ? {
          ...station,
          health: 'attention',
          backlog_count: 12,
          queue_depth: 12,
          station_load: 0.7,
          throughput_per_minute: 18,
          animation_hint: 'review_backlog',
          dominant_reason: 'Fila de revisao humana acima do normal.',
        }
      : station,
  ),
  timeline: [
    {
      id: 'evt_000140',
      event_sequence: 140,
      station_key: 'human_review',
      event_key: 'media.moderation.pending',
      severity: 'warning',
      urgency: 'high',
      title: 'Fila humana crescendo',
      summary: 'Doze midias aguardam revisao humana.',
      occurred_at: serverTime,
      render_group: 'review',
      animation_hint: 'review_backlog',
    },
  ],
};

export const eventOperationsDegradedSnapshotFixture: EventOperationsRoomSnapshot = {
  ...eventOperationsHumanReviewBottleneckSnapshotFixture,
  snapshot_version: 3,
  timeline_cursor: 'evt_000160',
  event_sequence: 160,
  health: {
    status: 'risk',
    dominant_station_key: 'wall',
    summary: 'Live degradado e wall em risco',
    updated_at: serverTime,
  },
  connection: {
    status: 'degraded',
    realtime_connected: false,
    last_connected_at: '2026-04-11T18:41:00Z',
    last_resync_at: '2026-04-11T18:40:30Z',
    degraded_reason: 'websocket_disconnected',
  },
  wall: {
    health: 'risk',
    online_players: 1,
    degraded_players: 1,
    offline_players: 1,
    current_item_id: 'media_120',
    next_item_id: null,
    confidence: 'low',
  },
  alerts: [
    {
      id: 'alert_wall_offline',
      severity: 'critical',
      urgency: 'critical',
      station_key: 'wall',
      title: 'Player do telao offline',
      summary: 'Um player do wall parou de enviar heartbeat.',
      occurred_at: serverTime,
    },
  ],
};
