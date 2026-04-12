import { beforeEach, describe, expect, it } from 'vitest';

import {
  eventOperationsGapDeltaFixture,
  eventOperationsHealthySnapshotFixture,
  eventOperationsStationDeltaFixture,
} from '../__fixtures__/operations-room.fixture';
import { eventOperationsTimelineStore } from './timeline-store';

describe('timeline-store sequence handling', () => {
  beforeEach(() => {
    eventOperationsTimelineStore.reset();
    eventOperationsTimelineStore.setFromRoom(eventOperationsHealthySnapshotFixture);
  });

  it('appends the next timeline entry in order and ignores a duplicate append', () => {
    const firstApply = eventOperationsTimelineStore.applyDelta(eventOperationsStationDeltaFixture);
    const duplicateApply = eventOperationsTimelineStore.applyDelta(eventOperationsStationDeltaFixture);

    const snapshot = eventOperationsTimelineStore.getSnapshot();

    expect(firstApply.status).toBe('applied');
    expect(duplicateApply).toEqual({
      status: 'ignored',
      reason: 'duplicate',
    });
    expect(snapshot.entries.map((entry) => entry.event_sequence)).toEqual([100, 101]);
  });

  it('keeps the rail able to receive an older queued timeline append only when it does not break the version contract', () => {
    eventOperationsTimelineStore.applyDelta(eventOperationsStationDeltaFixture);
    eventOperationsTimelineStore.setPage({
      ...eventOperationsTimelineStore.getSnapshot(),
      event_sequence: 102,
      timeline_cursor: 'evt_000102',
      server_time: '2026-04-11T18:42:25Z',
      entries: [
        ...eventOperationsTimelineStore.getSnapshot().entries,
        {
          ...eventOperationsStationDeltaFixture.timeline_entry!,
          id: 'evt_000102',
          event_sequence: 102,
          title: 'Galeria publicou de novo',
        },
      ],
    });

    const lateQueuedAppend = eventOperationsTimelineStore.applyDelta({
      ...eventOperationsStationDeltaFixture,
      timeline_entry: {
        ...eventOperationsStationDeltaFixture.timeline_entry!,
        id: 'evt_000101b',
        event_sequence: 101,
        title: 'Fila humana confirmada',
      },
    });

    expect(lateQueuedAppend.status).toBe('applied');
    expect(eventOperationsTimelineStore.getSnapshot().entries.map((entry) => entry.event_sequence)).toEqual([100, 101, 101, 102]);
    expect(eventOperationsTimelineStore.getSnapshot().event_sequence).toBe(102);
  });

  it('requests resync when a future gap lands before the missing sequence', () => {
    const result = eventOperationsTimelineStore.applyDelta({
      ...eventOperationsGapDeltaFixture,
      resync_required: false,
    });

    expect(result).toEqual({
      status: 'resync_required',
      reason: 'sequence_gap',
    });
  });
});
