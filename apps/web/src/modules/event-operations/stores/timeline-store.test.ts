import { beforeEach, describe, expect, it } from 'vitest';

import { eventOperationsHealthySnapshotFixture } from '../__fixtures__/operations-room.fixture';
import type { EventOperationsTimelinePage } from '../types';
import { eventOperationsTimelineStore } from './timeline-store';

function makeTimelinePage(): EventOperationsTimelinePage {
  return {
    schema_version: eventOperationsHealthySnapshotFixture.schema_version,
    snapshot_version: eventOperationsHealthySnapshotFixture.snapshot_version,
    timeline_cursor: eventOperationsHealthySnapshotFixture.timeline_cursor,
    event_sequence: eventOperationsHealthySnapshotFixture.event_sequence,
    server_time: eventOperationsHealthySnapshotFixture.server_time,
    entries: [
      {
        ...eventOperationsHealthySnapshotFixture.timeline[0],
        id: 'evt_000101',
        event_sequence: 101,
      },
      {
        ...eventOperationsHealthySnapshotFixture.timeline[0],
        id: 'evt_000100',
        event_sequence: 100,
      },
    ],
    filters: {
      cursor: null,
      station_key: null,
      severity: null,
      event_media_id: null,
      limit: 20,
    },
  };
}

describe('timeline-store', () => {
  beforeEach(() => {
    eventOperationsTimelineStore.reset();
  });

  it('hydrates the dedicated timeline page ordered from oldest to newest without mutating the input', () => {
    const input = makeTimelinePage();

    eventOperationsTimelineStore.setPage(input);

    const storedSnapshot = eventOperationsTimelineStore.getSnapshot();

    expect(storedSnapshot.entries.map((entry) => entry.event_sequence)).toEqual([100, 101]);
    expect(storedSnapshot.entries).not.toBe(input.entries);

    input.entries[0].title = 'Mutado fora da store';

    expect(eventOperationsTimelineStore.getSnapshot().entries[1]?.title).toBe('Midia publicada');
  });

  it('can seed the rail directly from the room snapshot while the dedicated history query is absent', () => {
    eventOperationsTimelineStore.setFromRoom(eventOperationsHealthySnapshotFixture);

    const storedSnapshot = eventOperationsTimelineStore.getSnapshot();

    expect(storedSnapshot.entries[0]?.title).toBe('Midia publicada');
    expect(storedSnapshot.timeline_cursor).toBe(eventOperationsHealthySnapshotFixture.timeline_cursor);
    expect(storedSnapshot.filters.limit).toBe(eventOperationsHealthySnapshotFixture.timeline.length);
  });
});
