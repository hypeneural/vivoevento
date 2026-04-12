import { useEffect } from 'react';

import { useQuery } from '@tanstack/react-query';

import { queryKeys } from '@/lib/query-client';

import { getEventOperationsBootRoom, getEventOperationsBootTimeline } from '../api';
import { eventOperationsHudStore } from '../stores/hud-store';
import { eventOperationsRoomStore } from '../stores/room-store';
import { eventOperationsTimelineStore } from '../stores/timeline-store';

interface EventOperationsBootPollingOptions {
  roomIntervalMs?: number | false;
  timelineIntervalMs?: number | false;
}

export function eventOperationsBootRoomQueryOptions(
  eventId: string,
  refetchInterval: number | false = 15 * 1000,
) {
  return {
    queryKey: queryKeys.operations.room(eventId),
    queryFn: () => getEventOperationsBootRoom(eventId),
    enabled: eventId !== '',
    staleTime: 60 * 1000,
    gcTime: 10 * 60 * 1000,
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
    refetchOnMount: false,
    refetchInterval,
    notifyOnChangeProps: ['data', 'error', 'fetchStatus', 'status'] as const,
  } as const;
}

export function eventOperationsBootTimelineQueryOptions(
  eventId: string,
  refetchInterval: number | false = false,
) {
  return {
    queryKey: queryKeys.operations.timeline(eventId, null),
    queryFn: () => getEventOperationsBootTimeline(eventId, { limit: 20 }),
    enabled: eventId !== '',
    staleTime: 60 * 1000,
    gcTime: 10 * 60 * 1000,
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
    refetchOnMount: false,
    refetchInterval,
    notifyOnChangeProps: ['data', 'error', 'fetchStatus', 'status'] as const,
  } as const;
}

export function useEventOperationsBoot(
  eventId: string,
  polling: EventOperationsBootPollingOptions = {},
) {
  const roomQuery = useQuery(
    eventOperationsBootRoomQueryOptions(eventId, polling.roomIntervalMs ?? 15 * 1000),
  );
  const timelineQuery = useQuery(
    eventOperationsBootTimelineQueryOptions(eventId, polling.timelineIntervalMs ?? false),
  );

  useEffect(() => {
    eventOperationsRoomStore.reset();
    eventOperationsTimelineStore.reset();
    eventOperationsHudStore.reset();
  }, [eventId]);

  useEffect(() => {
    if (!roomQuery.data) {
      return;
    }

    eventOperationsRoomStore.setSnapshot(roomQuery.data);
    eventOperationsHudStore.setRoom(roomQuery.data);

    if (!timelineQuery.data || timelineQuery.data.snapshot_version !== roomQuery.data.snapshot_version) {
      eventOperationsTimelineStore.setFromRoom(roomQuery.data);
    }
  }, [roomQuery.data, timelineQuery.data]);

  useEffect(() => {
    if (!timelineQuery.data) {
      return;
    }

    eventOperationsTimelineStore.setPage(timelineQuery.data);
  }, [timelineQuery.data]);

  return {
    roomQuery,
    timelineQuery,
    data: roomQuery.data,
    timeline: timelineQuery.data?.entries ?? roomQuery.data?.timeline ?? [],
    isLoading: roomQuery.isLoading,
    isError: roomQuery.isError,
    error: roomQuery.error,
  };
}
