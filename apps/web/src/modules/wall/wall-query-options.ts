import type {
  ApiWallInsightsResponse,
  ApiWallLiveSnapshotResponse,
  ApiWallSimulationResponse,
} from '@/lib/api-types';

const previousData = (current: ApiWallInsightsResponse | undefined) => current;
const previousLiveSnapshot = (current: ApiWallLiveSnapshotResponse | undefined) => current;
const previousSimulation = (current: ApiWallSimulationResponse | undefined) => current;

export const wallQueryOptions = {
  event: {
    staleTime: 60 * 1000,
    gcTime: 10 * 60 * 1000,
    refetchOnReconnect: false,
  },
  options: {
    staleTime: Infinity,
    gcTime: 60 * 60 * 1000,
    refetchOnWindowFocus: false,
  },
  settings: {
    staleTime: 60 * 1000,
    gcTime: 10 * 60 * 1000,
    refetchOnReconnect: false,
  },
  insights: {
    staleTime: 15 * 1000,
    gcTime: 5 * 60 * 1000,
    refetchOnWindowFocus: false,
    refetchOnReconnect: true,
    placeholderData: previousData,
  },
  diagnostics: {
    staleTime: 15 * 1000,
    gcTime: 5 * 60 * 1000,
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
  },
  liveSnapshot: {
    staleTime: 0,
    gcTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
    refetchOnReconnect: true,
    placeholderData: previousLiveSnapshot,
  },
  simulation: {
    staleTime: 15 * 1000,
    gcTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
    placeholderData: previousSimulation,
  },
} as const;
