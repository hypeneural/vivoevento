import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react';
import type { PropsWithChildren } from 'react';
import { act } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { EVENT_OPERATIONS_EVENT_NAMES } from '@eventovivo/shared-types/event-operations';

import {
  eventOperationsGapDeltaFixture,
  eventOperationsHealthySnapshotFixture,
  eventOperationsHumanReviewBottleneckSnapshotFixture,
  eventOperationsStationDeltaFixture,
} from '../__fixtures__/operations-room.fixture';
import { eventOperationsHudStore } from '../stores/hud-store';
import { eventOperationsRoomStore } from '../stores/room-store';
import { eventOperationsTimelineStore } from '../stores/timeline-store';
import { useEventOperationsRealtime } from './useEventOperationsRealtime';

const createEventOperationsPusherMock = vi.fn();
const disconnectEventOperationsPusherMock = vi.fn();
const roomQueryFnMock = vi.fn();
const timelineQueryFnMock = vi.fn();

vi.mock('../realtime/pusher', () => ({
  createEventOperationsPusher: (...args: unknown[]) => createEventOperationsPusherMock(...args),
  disconnectEventOperationsPusher: (...args: unknown[]) => disconnectEventOperationsPusherMock(...args),
}));

vi.mock('./useEventOperationsBoot', () => ({
  eventOperationsBootRoomQueryOptions: (eventId: string) => ({
    queryKey: ['operations', eventId, 'room'],
    queryFn: () => roomQueryFnMock(eventId),
  }),
  eventOperationsBootTimelineQueryOptions: (eventId: string) => ({
    queryKey: ['operations', eventId, 'timeline'],
    queryFn: () => timelineQueryFnMock(eventId),
  }),
}));

function createWrapper(queryClient: QueryClient) {
  return function Wrapper({ children }: PropsWithChildren) {
    return (
      <QueryClientProvider client={queryClient}>
        {children}
      </QueryClientProvider>
    );
  };
}

function createFakePusher() {
  const connectionHandlers = new Map<string, Set<(payload: any) => void>>();
  const channelHandlers = new Map<string, Set<(payload: any) => void>>();

  const connection = {
    bind: vi.fn((eventName: string, handler: (payload: any) => void) => {
      const handlers = connectionHandlers.get(eventName) ?? new Set();
      handlers.add(handler);
      connectionHandlers.set(eventName, handlers);
    }),
    unbind: vi.fn((eventName: string, handler: (payload: any) => void) => {
      connectionHandlers.get(eventName)?.delete(handler);
    }),
  };

  const channel = {
    bind: vi.fn((eventName: string, handler: (payload: any) => void) => {
      const handlers = channelHandlers.get(eventName) ?? new Set();
      handlers.add(handler);
      channelHandlers.set(eventName, handlers);
    }),
    unbind_all: vi.fn(() => {
      channelHandlers.clear();
    }),
  };

  return {
    connection,
    subscribe: vi.fn(() => channel),
    unsubscribe: vi.fn(),
    emitStateChange(current: string) {
      const handlers = connectionHandlers.get('state_change') ?? new Set();
      handlers.forEach((handler) => handler({ current }));
    },
    emitChannelEvent(eventName: string, payload: unknown) {
      const handlers = channelHandlers.get(eventName) ?? new Set();
      handlers.forEach((handler) => handler(payload));
    },
  };
}

function makeTimelinePage() {
  return {
    schema_version: eventOperationsHumanReviewBottleneckSnapshotFixture.schema_version,
    snapshot_version: eventOperationsHumanReviewBottleneckSnapshotFixture.snapshot_version,
    timeline_cursor: eventOperationsHumanReviewBottleneckSnapshotFixture.timeline_cursor,
    event_sequence: eventOperationsHumanReviewBottleneckSnapshotFixture.event_sequence,
    server_time: eventOperationsHumanReviewBottleneckSnapshotFixture.server_time,
    entries: eventOperationsHumanReviewBottleneckSnapshotFixture.timeline,
    filters: {
      cursor: null,
      station_key: null,
      severity: null,
      event_media_id: null,
      limit: 20,
    },
  };
}

describe('useEventOperationsRealtime', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    eventOperationsRoomStore.reset();
    eventOperationsTimelineStore.reset();
    eventOperationsHudStore.reset();
  });

  it('marks the room as degraded when no websocket client is available', async () => {
    createEventOperationsPusherMock.mockReturnValue(null);
    eventOperationsRoomStore.setSnapshot(eventOperationsHealthySnapshotFixture);
    const queryClient = new QueryClient({
      defaultOptions: {
        queries: {
          retry: false,
        },
      },
    });

    const { result } = renderHook(() => useEventOperationsRealtime('42'), {
      wrapper: createWrapper(queryClient),
    });

    await waitFor(() => {
      expect(result.current.connectionState).toBe('offline');
    });

    expect(result.current.statusMessage).toBe('Sala degradada: dados ao vivo indisponiveis');
    expect(eventOperationsRoomStore.getSnapshot()?.connection.status).toBe('offline');
  });

  it('applies monotonic realtime deltas, keeps the room connected and ignores duplicates', async () => {
    const fakePusher = createFakePusher();
    createEventOperationsPusherMock.mockReturnValue(fakePusher);
    eventOperationsRoomStore.setSnapshot(eventOperationsHealthySnapshotFixture);
    eventOperationsTimelineStore.setFromRoom(eventOperationsHealthySnapshotFixture);

    const queryClient = new QueryClient({
      defaultOptions: {
        queries: {
          retry: false,
        },
      },
    });

    const { result, unmount } = renderHook(() => useEventOperationsRealtime('42'), {
      wrapper: createWrapper(queryClient),
    });

    act(() => {
      fakePusher.emitStateChange('connected');
      fakePusher.emitChannelEvent(EVENT_OPERATIONS_EVENT_NAMES.stationDelta, {
        schema_version: eventOperationsStationDeltaFixture.schema_version,
        snapshot_version: eventOperationsStationDeltaFixture.snapshot_version,
        timeline_cursor: eventOperationsStationDeltaFixture.timeline_cursor,
        event_sequence: eventOperationsStationDeltaFixture.event_sequence,
        server_time: eventOperationsStationDeltaFixture.server_time,
        resync_required: false,
        station_delta: eventOperationsStationDeltaFixture.station_delta,
      });
      fakePusher.emitChannelEvent(EVENT_OPERATIONS_EVENT_NAMES.timelineAppended, {
        schema_version: eventOperationsStationDeltaFixture.schema_version,
        snapshot_version: eventOperationsStationDeltaFixture.snapshot_version,
        timeline_cursor: eventOperationsStationDeltaFixture.timeline_cursor,
        event_sequence: eventOperationsStationDeltaFixture.event_sequence,
        server_time: eventOperationsStationDeltaFixture.server_time,
        resync_required: false,
        timeline_entry: eventOperationsStationDeltaFixture.timeline_entry,
      });
      fakePusher.emitChannelEvent(EVENT_OPERATIONS_EVENT_NAMES.healthChanged, {
        schema_version: eventOperationsStationDeltaFixture.schema_version,
        snapshot_version: eventOperationsStationDeltaFixture.snapshot_version,
        timeline_cursor: eventOperationsStationDeltaFixture.timeline_cursor,
        event_sequence: eventOperationsStationDeltaFixture.event_sequence,
        server_time: eventOperationsStationDeltaFixture.server_time,
        resync_required: false,
        health: eventOperationsStationDeltaFixture.health,
      });
      fakePusher.emitChannelEvent(EVENT_OPERATIONS_EVENT_NAMES.timelineAppended, {
        schema_version: eventOperationsStationDeltaFixture.schema_version,
        snapshot_version: eventOperationsStationDeltaFixture.snapshot_version,
        timeline_cursor: eventOperationsStationDeltaFixture.timeline_cursor,
        event_sequence: eventOperationsStationDeltaFixture.event_sequence,
        server_time: eventOperationsStationDeltaFixture.server_time,
        resync_required: false,
        timeline_entry: eventOperationsStationDeltaFixture.timeline_entry,
      });
    });

    await waitFor(() => {
      expect(result.current.connectionState).toBe('connected');
    });

    expect(eventOperationsRoomStore.getSnapshot()?.event_sequence).toBe(101);
    expect(eventOperationsRoomStore.getSnapshot()?.counters.human_review_pending).toBe(3);
    expect(eventOperationsTimelineStore.getSnapshot().entries).toHaveLength(2);
    expect(eventOperationsHudStore.getSnapshot().hud?.connection_label).toBe('Conectado');

    unmount();

    expect(fakePusher.unsubscribe).toHaveBeenCalledWith('private-event.42.operations');
    expect(disconnectEventOperationsPusherMock).toHaveBeenCalled();
  });

  it('resyncs the room when a gap or explicit resync delta arrives', async () => {
    const fakePusher = createFakePusher();
    createEventOperationsPusherMock.mockReturnValue(fakePusher);
    eventOperationsRoomStore.setSnapshot(eventOperationsHealthySnapshotFixture);
    eventOperationsTimelineStore.setFromRoom(eventOperationsHealthySnapshotFixture);
    roomQueryFnMock.mockResolvedValue(eventOperationsHumanReviewBottleneckSnapshotFixture);
    timelineQueryFnMock.mockResolvedValue(makeTimelinePage());

    const queryClient = new QueryClient({
      defaultOptions: {
        queries: {
          retry: false,
        },
      },
    });

    const { result } = renderHook(() => useEventOperationsRealtime('42'), {
      wrapper: createWrapper(queryClient),
    });

    act(() => {
      fakePusher.emitStateChange('connected');
      fakePusher.emitChannelEvent(EVENT_OPERATIONS_EVENT_NAMES.stationDelta, {
        schema_version: eventOperationsGapDeltaFixture.schema_version,
        snapshot_version: eventOperationsGapDeltaFixture.snapshot_version,
        timeline_cursor: eventOperationsGapDeltaFixture.timeline_cursor,
        event_sequence: eventOperationsGapDeltaFixture.event_sequence,
        server_time: eventOperationsGapDeltaFixture.server_time,
        resync_required: eventOperationsGapDeltaFixture.resync_required,
        station_delta: eventOperationsGapDeltaFixture.station_delta,
      });
    });

    await waitFor(() => {
      expect(roomQueryFnMock).toHaveBeenCalledWith('42');
      expect(timelineQueryFnMock).toHaveBeenCalledWith('42');
    });

    await waitFor(() => {
      expect(result.current.connectionState).toBe('connected');
      expect(result.current.statusMessage).toBe('Resync concluido');
    });

    expect(eventOperationsRoomStore.getSnapshot()?.snapshot_version).toBe(2);
    expect(eventOperationsRoomStore.getSnapshot()?.health.status).toBe('attention');
    expect(eventOperationsTimelineStore.getSnapshot().event_sequence).toBe(140);
  });
});
