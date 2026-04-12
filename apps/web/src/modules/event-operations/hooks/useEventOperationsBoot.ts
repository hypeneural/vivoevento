import { useQuery } from '@tanstack/react-query';

import { queryKeys } from '@/lib/query-client';

import { getEventOperationsBootRoom } from '../api';

const keepPreviousRoom = <T,>(previous: T | undefined) => previous;

export function eventOperationsBootQueryOptions(eventId: string) {
  return {
    queryKey: queryKeys.operations.room(eventId),
    queryFn: () => getEventOperationsBootRoom(eventId),
    enabled: eventId !== '',
    staleTime: 60 * 1000,
    gcTime: 10 * 60 * 1000,
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
    refetchOnMount: false,
    refetchInterval: 15 * 1000,
    placeholderData: keepPreviousRoom,
    notifyOnChangeProps: ['data', 'error', 'fetchStatus', 'status'] as const,
  } as const;
}

export function useEventOperationsBoot(eventId: string) {
  return useQuery(eventOperationsBootQueryOptions(eventId));
}
