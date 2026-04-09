import { useQuery } from '@tanstack/react-query';

import { queryKeys } from '@/lib/query-client';

import { getEventWallLiveSnapshot } from '../api';
import { wallQueryOptions } from '../wall-query-options';

interface UseWallLiveSnapshotOptions {
  refetchInterval?: number | false;
}

export function useWallLiveSnapshot(eventId: string, options: UseWallLiveSnapshotOptions = {}) {
  return useQuery({
    queryKey: queryKeys.wall.liveSnapshot(eventId),
    queryFn: () => getEventWallLiveSnapshot(eventId),
    enabled: eventId !== '',
    ...wallQueryOptions.liveSnapshot,
    refetchInterval: options.refetchInterval,
  });
}
