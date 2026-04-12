import { beforeEach, describe, expect, it } from 'vitest';

import { eventOperationsHealthySnapshotFixture } from '../__fixtures__/operations-room.fixture';
import { eventOperationsRoomStore } from './room-store';

describe('room-store', () => {
  beforeEach(() => {
    eventOperationsRoomStore.reset();
  });

  it('replaces the snapshot object and keeps the stored copy isolated from external mutation', () => {
    const input = {
      ...eventOperationsHealthySnapshotFixture,
      health: {
        ...eventOperationsHealthySnapshotFixture.health,
      },
    };

    eventOperationsRoomStore.setSnapshot(input);

    const storedSnapshot = eventOperationsRoomStore.getSnapshot();

    expect(storedSnapshot).not.toBeNull();
    expect(storedSnapshot).not.toBe(input);

    input.health.summary = 'Mutado fora da store';

    expect(eventOperationsRoomStore.getSnapshot()?.health.summary).toBe('Operacao saudavel');

    eventOperationsRoomStore.setSnapshot({
      ...eventOperationsHealthySnapshotFixture,
      snapshot_version: 2,
    });

    expect(eventOperationsRoomStore.getSnapshot()?.snapshot_version).toBe(2);
  });

  it('resets back to an empty room snapshot', () => {
    eventOperationsRoomStore.setSnapshot(eventOperationsHealthySnapshotFixture);

    expect(eventOperationsRoomStore.getSnapshot()?.event.id).toBe(42);

    eventOperationsRoomStore.reset();

    expect(eventOperationsRoomStore.getSnapshot()).toBeNull();
  });
});
