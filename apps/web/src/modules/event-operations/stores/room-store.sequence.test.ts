import { beforeEach, describe, expect, it } from 'vitest';

import {
  eventOperationsGapDeltaFixture,
  eventOperationsHealthSameSequenceDeltaFixture,
  eventOperationsHealthySnapshotFixture,
  eventOperationsStationDeltaFixture,
} from '../__fixtures__/operations-room.fixture';
import { eventOperationsRoomStore } from './room-store';

describe('room-store sequence handling', () => {
  beforeEach(() => {
    eventOperationsRoomStore.reset();
    eventOperationsRoomStore.setSnapshot(eventOperationsHealthySnapshotFixture);
  });

  it('applies the next monotonic delta and accepts a second kind on the same event sequence without duplicating', () => {
    const firstApply = eventOperationsRoomStore.applyDelta(eventOperationsStationDeltaFixture);
    const secondApply = eventOperationsRoomStore.applyDelta(eventOperationsHealthSameSequenceDeltaFixture);
    const duplicateApply = eventOperationsRoomStore.applyDelta(eventOperationsHealthSameSequenceDeltaFixture);

    const snapshot = eventOperationsRoomStore.getSnapshot();

    expect(firstApply.status).toBe('applied');
    expect(secondApply.status).toBe('applied');
    expect(duplicateApply).toEqual({
      status: 'ignored',
      reason: 'duplicate',
    });
    expect(snapshot?.event_sequence).toBe(101);
    expect(snapshot?.health.status).toBe('attention');
    expect(snapshot?.counters.human_review_pending).toBe(3);
    expect(snapshot?.timeline).toHaveLength(2);
  });

  it('freezes incremental application when the delta requests resync or arrives with a sequence gap', () => {
    const result = eventOperationsRoomStore.applyDelta(eventOperationsGapDeltaFixture);

    expect(result).toEqual({
      status: 'resync_required',
      reason: 'explicit_resync',
    });
    expect(eventOperationsRoomStore.getSnapshot()?.event_sequence).toBe(100);
  });
});
