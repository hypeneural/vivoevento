import type { ApiWallInsightsResponse } from '@/lib/api-types';

const previousData = (current: ApiWallInsightsResponse | undefined) => current;

export const wallQueryOptions = {
  event: {
    staleTime: 30 * 1000,
    gcTime: 10 * 60 * 1000,
  },
  options: {
    staleTime: Infinity,
    gcTime: 60 * 60 * 1000,
    refetchOnWindowFocus: false,
  },
  settings: {
    staleTime: 30 * 1000,
    gcTime: 10 * 60 * 1000,
  },
  insights: {
    staleTime: 15 * 1000,
    gcTime: 5 * 60 * 1000,
    refetchOnWindowFocus: false,
    refetchOnReconnect: true,
    placeholderData: previousData,
  },
  diagnostics: {
    staleTime: 0,
    gcTime: 5 * 60 * 1000,
    refetchOnWindowFocus: false,
  },
  liveSnapshot: {
    staleTime: 0,
    gcTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  },
} as const;
