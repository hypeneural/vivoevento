import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react';
import type { PropsWithChildren } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { queryKeys } from '@/lib/query-client';

import { eventOperationsHealthySnapshotFixture } from '../__fixtures__/operations-room.fixture';
import type { EventOperationsTimelinePage, EventOperationsV0Room } from '../types';
import { eventOperationsHudStore } from '../stores/hud-store';
import { eventOperationsRoomStore } from '../stores/room-store';
import { eventOperationsTimelineStore } from '../stores/timeline-store';
import {
  eventOperationsBootRoomQueryOptions,
  eventOperationsBootTimelineQueryOptions,
  useEventOperationsBoot,
} from './useEventOperationsBoot';

const getEventOperationsBootRoomMock = vi.fn();
const getEventOperationsBootTimelineMock = vi.fn();

vi.mock('../api', () => ({
  getEventOperationsBootRoom: (...args: unknown[]) => getEventOperationsBootRoomMock(...args),
  getEventOperationsBootTimeline: (...args: unknown[]) => getEventOperationsBootTimelineMock(...args),
}));

function createWrapper() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return function Wrapper({ children }: PropsWithChildren) {
    return (
      <QueryClientProvider client={queryClient}>
        {children}
      </QueryClientProvider>
    );
  };
}

function makeBootRoom(): EventOperationsV0Room {
  return eventOperationsHealthySnapshotFixture;
}

function makeTimelinePage(): EventOperationsTimelinePage {
  return {
    schema_version: eventOperationsHealthySnapshotFixture.schema_version,
    snapshot_version: eventOperationsHealthySnapshotFixture.snapshot_version,
    timeline_cursor: eventOperationsHealthySnapshotFixture.timeline_cursor,
    event_sequence: eventOperationsHealthySnapshotFixture.event_sequence,
    server_time: eventOperationsHealthySnapshotFixture.server_time,
    entries: eventOperationsHealthySnapshotFixture.timeline,
    filters: {
      cursor: null,
      station_key: null,
      severity: null,
      event_media_id: null,
      limit: 20,
    },
  };
}

describe('useEventOperationsBoot', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    eventOperationsRoomStore.reset();
    eventOperationsTimelineStore.reset();
    eventOperationsHudStore.reset();
  });

  it('hydrates the dedicated room and timeline endpoints into the external stores', async () => {
    getEventOperationsBootRoomMock.mockResolvedValue(makeBootRoom());
    getEventOperationsBootTimelineMock.mockResolvedValue(makeTimelinePage());

    const { result } = renderHook(() => useEventOperationsBoot('42'), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(result.current.data?.event.title).toBe('Casamento Ana e Bruno');
    });

    await waitFor(() => {
      expect(eventOperationsRoomStore.getSnapshot()?.event.id).toBe(42);
      expect(eventOperationsTimelineStore.getSnapshot().entries[0]?.title).toBe('Midia publicada');
      expect(eventOperationsHudStore.getSnapshot().hud?.event_title).toBe('Casamento Ana e Bruno');
    });

    expect(result.current.timeline[0]?.title).toBe('Midia publicada');
    expect(getEventOperationsBootRoomMock).toHaveBeenCalledWith('42');
    expect(getEventOperationsBootTimelineMock).toHaveBeenCalledWith('42', { limit: 20 });
  });

  it('falls back to the room timeline when the dedicated history endpoint is temporarily unavailable', async () => {
    getEventOperationsBootRoomMock.mockResolvedValue(makeBootRoom());
    getEventOperationsBootTimelineMock.mockRejectedValue(new Error('timeline unavailable'));

    const { result } = renderHook(() => useEventOperationsBoot('42'), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(result.current.data?.event.id).toBe(42);
    });

    await waitFor(() => {
      expect(eventOperationsTimelineStore.getSnapshot().entries[0]?.title).toBe('Midia publicada');
    });

    expect(result.current.isError).toBe(false);
    expect(result.current.timeline[0]?.title).toBe('Midia publicada');
  });

  it('freezes the room boot query as high-stale polling and keeps the timeline query off the live hot path', () => {
    const roomOptions = eventOperationsBootRoomQueryOptions('42');
    const timelineOptions = eventOperationsBootTimelineQueryOptions('42');

    expect(roomOptions.queryKey).toEqual(queryKeys.operations.room('42'));
    expect(roomOptions.staleTime).toBe(60 * 1000);
    expect(roomOptions.refetchOnWindowFocus).toBe(false);
    expect(roomOptions.refetchOnReconnect).toBe(false);
    expect(roomOptions.refetchOnMount).toBe(false);
    expect(roomOptions.refetchInterval).toBe(15 * 1000);
    expect(roomOptions.notifyOnChangeProps).toEqual(['data', 'error', 'fetchStatus', 'status']);

    expect(timelineOptions.queryKey).toEqual(queryKeys.operations.timeline('42', null));
    expect(timelineOptions.staleTime).toBe(60 * 1000);
    expect(timelineOptions.refetchOnWindowFocus).toBe(false);
    expect(timelineOptions.refetchOnReconnect).toBe(false);
    expect(timelineOptions.refetchOnMount).toBe(false);
    expect(timelineOptions.refetchInterval).toBe(false);
    expect(timelineOptions.notifyOnChangeProps).toEqual(['data', 'error', 'fetchStatus', 'status']);
  });
});
