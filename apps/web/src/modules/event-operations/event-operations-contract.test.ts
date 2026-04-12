import { describe, expect, it } from 'vitest';

import {
  EVENT_OPERATIONS_EVENT_NAMES,
  EVENT_OPERATIONS_SCHEMA_VERSION,
  EVENT_OPERATIONS_STATION_KEYS,
  type EventOperationsDelta,
  type EventOperationsRoomSnapshot,
} from '@eventovivo/shared-types/event-operations';

describe('event operations contract', () => {
  it('freezes the versioned live contract fields shared by snapshots and deltas', () => {
    const base = {
      schema_version: EVENT_OPERATIONS_SCHEMA_VERSION,
      snapshot_version: 42,
      timeline_cursor: 'evt_000981',
      event_sequence: 981,
      server_time: '2026-04-11T18:42:15Z',
    };

    const snapshot: Pick<
      EventOperationsRoomSnapshot,
      'schema_version' | 'snapshot_version' | 'timeline_cursor' | 'event_sequence' | 'server_time'
    > = base;
    const delta: Pick<
      EventOperationsDelta,
      'schema_version' | 'snapshot_version' | 'timeline_cursor' | 'event_sequence' | 'server_time'
    > = base;

    expect(snapshot).toEqual(base);
    expect(delta).toEqual(base);
  });

  it('declares stable station keys and broadcast event names', () => {
    expect(EVENT_OPERATIONS_STATION_KEYS).toEqual([
      'intake',
      'download',
      'variants',
      'safety',
      'intelligence',
      'human_review',
      'gallery',
      'wall',
      'feedback',
      'alerts',
    ]);

    expect(EVENT_OPERATIONS_EVENT_NAMES).toEqual({
      stationDelta: 'operations.station.delta',
      timelineAppended: 'operations.timeline.appended',
      alertCreated: 'operations.alert.created',
      healthChanged: 'operations.health.changed',
      snapshotBoot: 'operations.snapshot.boot',
    });
  });

  it('keeps operational state separate from animation runtime state', () => {
    const station = {
      station_key: 'human_review',
      label: 'Moderacao humana',
      health: 'attention',
      backlog_count: 12,
      queue_depth: 12,
      station_load: 0.7,
      throughput_per_minute: 18,
      recent_items: [],
      animation_hint: 'review_backlog',
      render_group: 'moderation',
      updated_at: '2026-04-11T18:42:15Z',
    } satisfies EventOperationsRoomSnapshot['stations'][number];

    expect(station).not.toHaveProperty('x');
    expect(station).not.toHaveProperty('y');
    expect(station).not.toHaveProperty('frame');
    expect(station).not.toHaveProperty('direction');
  });
});
