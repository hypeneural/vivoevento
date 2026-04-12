import {
  EVENT_OPERATIONS_SCHEMA_VERSION,
  type EventOperationsDelta,
} from '@eventovivo/shared-types/event-operations';

const serverTime = '2026-04-11T18:42:20Z';

export const eventOperationsStationDeltaFixture: EventOperationsDelta = {
  schema_version: EVENT_OPERATIONS_SCHEMA_VERSION,
  snapshot_version: 1,
  timeline_cursor: 'evt_000101',
  event_sequence: 101,
  server_time: serverTime,
  kind: 'station.delta',
  broadcast_priority: 'operational_normal',
  station_delta: {
    station_key: 'human_review',
    patch: {
      health: 'attention',
      backlog_count: 3,
      queue_depth: 3,
      station_load: 0.42,
      throughput_per_minute: 6,
      animation_hint: 'review_backlog',
      dominant_reason: 'Tres itens chegaram na fila humana.',
      updated_at: serverTime,
    },
  },
  timeline_entry: {
    id: 'evt_000101',
    event_sequence: 101,
    station_key: 'human_review',
    event_key: 'media.moderation.pending',
    severity: 'warning',
    urgency: 'high',
    title: 'Fila humana crescendo',
    summary: 'Tres midias aguardam decisao humana.',
    occurred_at: serverTime,
    render_group: 'review',
    animation_hint: 'review_backlog',
  },
  health: {
    status: 'attention',
    dominant_station_key: 'human_review',
    summary: 'Atencao em Moderacao humana',
    updated_at: serverTime,
  },
  resync_required: false,
};

export const eventOperationsHealthSameSequenceDeltaFixture: EventOperationsDelta = {
  schema_version: EVENT_OPERATIONS_SCHEMA_VERSION,
  snapshot_version: 1,
  timeline_cursor: 'evt_000101',
  event_sequence: 101,
  server_time: serverTime,
  kind: 'health.changed',
  broadcast_priority: 'critical_immediate',
  health: {
    status: 'attention',
    dominant_station_key: 'human_review',
    summary: 'Atencao em Moderacao humana',
    updated_at: serverTime,
  },
  resync_required: false,
};

export const eventOperationsAlertDeltaFixture: EventOperationsDelta = {
  schema_version: EVENT_OPERATIONS_SCHEMA_VERSION,
  snapshot_version: 3,
  timeline_cursor: 'evt_000161',
  event_sequence: 161,
  server_time: serverTime,
  kind: 'alert.created',
  broadcast_priority: 'critical_immediate',
  alert: {
    id: 'alert_wall_offline',
    severity: 'critical',
    urgency: 'critical',
    station_key: 'wall',
    title: 'Player do telao offline',
    summary: 'Um player do wall parou de enviar heartbeat.',
    occurred_at: serverTime,
  },
  timeline_entry: {
    id: 'evt_000161',
    event_sequence: 161,
    station_key: 'wall',
    event_key: 'wall.health.changed',
    severity: 'critical',
    urgency: 'critical',
    title: 'Wall em risco',
    summary: 'Um player do wall ficou offline.',
    occurred_at: serverTime,
    render_group: 'wall',
    animation_hint: 'critical_alert',
  },
  health: {
    status: 'risk',
    dominant_station_key: 'wall',
    summary: 'Risco em Telao',
    updated_at: serverTime,
  },
  resync_required: false,
};

export const eventOperationsGapDeltaFixture: EventOperationsDelta = {
  schema_version: EVENT_OPERATIONS_SCHEMA_VERSION,
  snapshot_version: 1,
  timeline_cursor: 'evt_000110',
  event_sequence: 110,
  server_time: serverTime,
  kind: 'station.delta',
  broadcast_priority: 'operational_normal',
  resync_required: true,
  station_delta: {
    station_key: 'intake',
    patch: {
      throughput_per_minute: 24,
      station_load: 0.85,
      queue_depth: 8,
      animation_hint: 'intake_pulse',
      updated_at: serverTime,
    },
  },
};
