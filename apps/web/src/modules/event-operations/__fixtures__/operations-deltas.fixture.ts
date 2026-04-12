import {
  EVENT_OPERATIONS_SCHEMA_VERSION,
  type EventOperationsDelta,
} from '@eventovivo/shared-types/event-operations';

const serverTime = '2026-04-11T18:42:20Z';

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
