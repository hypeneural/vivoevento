import { useSyncExternalStore } from 'react';

import {
  EVENT_OPERATIONS_SCHEMA_VERSION,
  type EventOperationsDelta,
} from '@eventovivo/shared-types/event-operations';

import type { EventOperationsTimelinePage, EventOperationsV0Room } from '../types';
import type { EventOperationsDeltaApplyResult } from './room-store';

type Listener = () => void;

export interface EventOperationsTimelineStoreSnapshot extends EventOperationsTimelinePage {}

const EMPTY_TIMELINE_SNAPSHOT: EventOperationsTimelineStoreSnapshot = {
  schema_version: EVENT_OPERATIONS_SCHEMA_VERSION,
  snapshot_version: 0,
  timeline_cursor: null,
  event_sequence: 0,
  server_time: '',
  entries: [],
  filters: {
    cursor: null,
    station_key: null,
    severity: null,
    event_media_id: null,
    limit: 20,
  },
};

function cloneSnapshot<T>(value: T): T {
  if (typeof structuredClone === 'function') {
    return structuredClone(value);
  }

  return JSON.parse(JSON.stringify(value)) as T;
}

function orderEntries<T extends { event_sequence: number }>(entries: T[]): T[] {
  return [...entries].sort((left, right) => left.event_sequence - right.event_sequence);
}

class EventOperationsTimelineStore {
  private snapshot: EventOperationsTimelineStoreSnapshot = EMPTY_TIMELINE_SNAPSHOT;

  private readonly listeners = new Set<Listener>();

  subscribe = (listener: Listener) => {
    this.listeners.add(listener);

    return () => {
      this.listeners.delete(listener);
    };
  };

  getSnapshot = () => this.snapshot;

  getServerSnapshot = () => this.snapshot;

  setPage(page: EventOperationsTimelinePage) {
    const cloned = cloneSnapshot(page);

    this.snapshot = {
      ...cloned,
      entries: orderEntries(cloned.entries),
    };

    this.emit();

    return this.snapshot;
  }

  setFromRoom(room: EventOperationsV0Room) {
    this.snapshot = {
      schema_version: room.schema_version,
      snapshot_version: room.snapshot_version,
      timeline_cursor: room.timeline_cursor,
      event_sequence: room.event_sequence,
      server_time: room.server_time,
      entries: cloneSnapshot(orderEntries(room.timeline)),
      filters: {
        cursor: null,
        station_key: null,
        severity: null,
        event_media_id: null,
        limit: room.timeline.length,
      },
    };

    this.emit();

    return this.snapshot;
  }

  applyDelta(delta: EventOperationsDelta): EventOperationsDeltaApplyResult {
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

    if (delta.event_sequence > this.snapshot.event_sequence + 1) {
      return {
        status: 'resync_required',
        reason: 'sequence_gap',
      };
    }

    if (!delta.timeline_entry) {
      if (delta.event_sequence <= this.snapshot.event_sequence) {
        return {
          status: 'ignored',
          reason: delta.event_sequence === this.snapshot.event_sequence ? 'duplicate' : 'stale',
        };
      }

      this.snapshot = {
        ...this.snapshot,
        schema_version: delta.schema_version,
        snapshot_version: delta.snapshot_version,
        timeline_cursor: delta.timeline_cursor,
        event_sequence: delta.event_sequence,
        server_time: delta.server_time,
      };
      this.emit();

      return {
        status: 'applied',
      };
    }

    if (this.snapshot.entries.some((entry) => entry.id === delta.timeline_entry.id)) {
      return {
        status: 'ignored',
        reason: 'duplicate',
      };
    }

    const nextEntries = [
      ...this.snapshot.entries,
      delta.timeline_entry,
    ];

    this.snapshot = {
      ...this.snapshot,
      schema_version: delta.schema_version,
      snapshot_version: delta.snapshot_version,
      timeline_cursor: delta.event_sequence > this.snapshot.event_sequence
        ? delta.timeline_cursor
        : this.snapshot.timeline_cursor,
      event_sequence: Math.max(this.snapshot.event_sequence, delta.event_sequence),
      server_time: delta.event_sequence > this.snapshot.event_sequence
        ? delta.server_time
        : this.snapshot.server_time,
      entries: orderEntries(nextEntries).slice(-Math.max(this.snapshot.filters.limit, 20)),
    };

    this.emit();

    return {
      status: 'applied',
    };
  }

  reset() {
    this.snapshot = EMPTY_TIMELINE_SNAPSHOT;
    this.emit();
  }

  private emit() {
    this.listeners.forEach((listener) => listener());
  }
}

export const eventOperationsTimelineStore = new EventOperationsTimelineStore();

export function useEventOperationsTimelineSnapshot() {
  return useSyncExternalStore(
    eventOperationsTimelineStore.subscribe,
    eventOperationsTimelineStore.getSnapshot,
    eventOperationsTimelineStore.getServerSnapshot,
  );
}
