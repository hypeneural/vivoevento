import { useSyncExternalStore } from 'react';

import {
  EVENT_OPERATIONS_SCHEMA_VERSION,
  type EventOperationsConnectionSummary,
  type EventOperationsDelta,
  type EventOperationsHealthSummary,
  type EventOperationsRoomSnapshot,
  type EventOperationsStationState,
} from '@eventovivo/shared-types/event-operations';

import type { EventOperationsV0Room } from '../types';

type Listener = () => void;

export interface EventOperationsDeltaApplyResult {
  status: 'applied' | 'ignored' | 'resync_required' | 'missing_snapshot';
  reason?: 'duplicate' | 'stale' | 'schema_mismatch' | 'snapshot_mismatch' | 'sequence_gap' | 'explicit_resync';
}

function cloneSnapshot<T>(value: T): T {
  if (typeof structuredClone === 'function') {
    return structuredClone(value);
  }

  return JSON.parse(JSON.stringify(value)) as T;
}

function sortAlerts<T extends { occurred_at: string }>(alerts: T[]): T[] {
  return [...alerts].sort((left, right) => right.occurred_at.localeCompare(left.occurred_at));
}

function sortTimeline<T extends { event_sequence: number }>(entries: T[]): T[] {
  return [...entries].sort((left, right) => left.event_sequence - right.event_sequence);
}

function mapHealthFromQueue(queueDepth: number, stationLoad: number, previous: EventOperationsStationState['health']) {
  if (previous === 'risk' || previous === 'offline') {
    return previous;
  }

  if (queueDepth > 0 || stationLoad >= 0.6) {
    return 'attention' as const;
  }

  return 'healthy' as const;
}

function recomputeCounters(snapshot: EventOperationsV0Room) {
  const humanReviewStation = snapshot.stations.find((station) => station.station_key === 'human_review');

  return {
    ...snapshot.counters,
    backlog_total: snapshot.stations.reduce((total, station) => total + station.queue_depth, 0),
    human_review_pending: humanReviewStation?.queue_depth ?? 0,
  };
}

function upsertAlert(snapshot: EventOperationsV0Room, delta: EventOperationsDelta) {
  if (!delta.alert) {
    return snapshot.alerts;
  }

  const nextAlerts = [
    delta.alert,
    ...snapshot.alerts.filter((alert) => alert.id !== delta.alert?.id),
  ];

  return sortAlerts(nextAlerts).slice(0, 5);
}

function upsertTimeline(snapshot: EventOperationsV0Room, delta: EventOperationsDelta) {
  if (!delta.timeline_entry) {
    return snapshot.timeline;
  }

  const nextTimeline = [
    ...snapshot.timeline.filter((entry) => entry.id !== delta.timeline_entry?.id),
    delta.timeline_entry,
  ];

  return sortTimeline(nextTimeline).slice(-20);
}

function applyStationPatch(
  stations: EventOperationsStationState[],
  delta: EventOperationsDelta,
): EventOperationsStationState[] {
  if (!delta.station_delta) {
    return stations;
  }

  return stations.map((station) => {
    if (station.station_key !== delta.station_delta?.station_key) {
      return station;
    }

    const patch = delta.station_delta.patch;
    const nextQueueDepth = patch.queue_depth ?? station.queue_depth;
    const nextStationLoad = patch.station_load ?? station.station_load;

    return {
      ...station,
      ...patch,
      backlog_count: patch.backlog_count ?? nextQueueDepth,
      queue_depth: nextQueueDepth,
      station_load: nextStationLoad,
      health: patch.health ?? mapHealthFromQueue(nextQueueDepth, nextStationLoad, station.health),
      recent_items: patch.recent_items ?? station.recent_items,
      updated_at: patch.updated_at ?? station.updated_at,
    };
  });
}

function needsRealtimeConnectionUpdate(
  current: EventOperationsConnectionSummary,
  next: Partial<EventOperationsConnectionSummary>,
): boolean {
  return (
    current.status !== next.status
    || current.realtime_connected !== next.realtime_connected
    || current.last_connected_at !== next.last_connected_at
    || current.last_resync_at !== next.last_resync_at
    || current.degraded_reason !== next.degraded_reason
  );
}

class EventOperationsRoomStore {
  private snapshot: EventOperationsV0Room | null = null;

  private activeSequence: number | null = null;

  private activeKinds = new Set<EventOperationsDelta['kind']>();

  private readonly listeners = new Set<Listener>();

  subscribe = (listener: Listener) => {
    this.listeners.add(listener);

    return () => {
      this.listeners.delete(listener);
    };
  };

  getSnapshot = () => this.snapshot;

  getServerSnapshot = () => this.snapshot;

  setSnapshot(next: EventOperationsV0Room) {
    this.snapshot = cloneSnapshot(next);
    this.activeSequence = null;
    this.activeKinds = new Set();
    this.emit();

    return this.snapshot;
  }

  applyDelta(delta: EventOperationsDelta): EventOperationsDeltaApplyResult {
    if (!this.snapshot) {
      return {
        status: 'missing_snapshot',
      };
    }

    if (delta.schema_version !== EVENT_OPERATIONS_SCHEMA_VERSION) {
      return {
        status: 'resync_required',
        reason: 'schema_mismatch',
      };
    }

    if (delta.resync_required) {
      return {
        status: 'resync_required',
        reason: 'explicit_resync',
      };
    }

    if (delta.snapshot_version !== this.snapshot.snapshot_version) {
      return {
        status: 'resync_required',
        reason: 'snapshot_mismatch',
      };
    }

    if (delta.event_sequence <= this.snapshot.event_sequence) {
      if (
        delta.event_sequence === this.snapshot.event_sequence
        && this.activeSequence === delta.event_sequence
        && !this.activeKinds.has(delta.kind)
      ) {
        this.activeKinds.add(delta.kind);
      } else {
        return {
          status: 'ignored',
          reason: delta.event_sequence === this.snapshot.event_sequence ? 'duplicate' : 'stale',
        };
      }
    } else if (delta.event_sequence !== this.snapshot.event_sequence + 1) {
      return {
        status: 'resync_required',
        reason: 'sequence_gap',
      };
    } else {
      this.activeSequence = delta.event_sequence;
      this.activeKinds = new Set([delta.kind]);
    }

    const nextStations = applyStationPatch(this.snapshot.stations, delta);
    const nextTimeline = upsertTimeline(this.snapshot, delta);
    const nextHealth: EventOperationsHealthSummary = delta.health ?? this.snapshot.health;

    const nextSnapshot: EventOperationsV0Room = {
      ...this.snapshot,
      schema_version: delta.schema_version,
      snapshot_version: delta.snapshot_version,
      timeline_cursor: delta.timeline_cursor,
      event_sequence: delta.event_sequence,
      server_time: delta.server_time,
      stations: nextStations,
      alerts: upsertAlert(this.snapshot, delta),
      timeline: nextTimeline,
      health: nextHealth,
    };

    nextSnapshot.counters = recomputeCounters(nextSnapshot);

    this.snapshot = nextSnapshot;
    this.emit();

    return {
      status: 'applied',
    };
  }

  setRealtimeConnection(next: Partial<EventOperationsConnectionSummary>) {
    if (!this.snapshot) {
      return null;
    }

    if (!needsRealtimeConnectionUpdate(this.snapshot.connection, next)) {
      return this.snapshot;
    }

    this.snapshot = {
      ...this.snapshot,
      connection: {
        ...this.snapshot.connection,
        ...next,
      },
    };

    this.emit();

    return this.snapshot;
  }

  applySnapshotBoot(snapshot: EventOperationsRoomSnapshot) {
    return this.setSnapshot(snapshot);
  }

  reset() {
    this.snapshot = null;
    this.activeSequence = null;
    this.activeKinds = new Set();
    this.emit();
  }

  private emit() {
    this.listeners.forEach((listener) => listener());
  }
}

export const eventOperationsRoomStore = new EventOperationsRoomStore();

export function useEventOperationsRoomSnapshot() {
  return useSyncExternalStore(
    eventOperationsRoomStore.subscribe,
    eventOperationsRoomStore.getSnapshot,
    eventOperationsRoomStore.getServerSnapshot,
  );
}
