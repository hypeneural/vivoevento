import { useQuery } from '@tanstack/react-query';

import { queryKeys } from '@/lib/query-client';

import { getEventWallInsights } from '../api';
import { wallQueryOptions } from '../wall-query-options';

interface UseWallTopInsightsOptions {
  refetchInterval?: number | false;
}

export function useWallTopInsights(eventId: string, options: UseWallTopInsightsOptions = {}) {
  return useQuery({
    queryKey: queryKeys.wall.insights(eventId),
    queryFn: () => getEventWallInsights(eventId),
    enabled: eventId !== '',
    ...wallQueryOptions.insights,
    refetchInterval: options.refetchInterval,
  });
}
