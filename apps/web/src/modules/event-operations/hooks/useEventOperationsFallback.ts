export type EventOperationsRealtimeState =
  | 'idle'
  | 'connecting'
  | 'connected'
  | 'reconnecting'
  | 'resyncing'
  | 'degraded'
  | 'offline';

export interface EventOperationsFallbackState {
  isPollingFallbackActive: boolean;
  roomIntervalMs: number | false;
  timelineIntervalMs: number | false;
}

const POLLING_DISABLED: EventOperationsFallbackState = {
  isPollingFallbackActive: false,
  roomIntervalMs: false,
  timelineIntervalMs: false,
};

const POLLING_ENABLED: EventOperationsFallbackState = {
  isPollingFallbackActive: true,
  roomIntervalMs: 15000,
  timelineIntervalMs: 20000,
};

export function useEventOperationsFallback(
  realtimeState: EventOperationsRealtimeState,
): EventOperationsFallbackState {
  if (realtimeState === 'degraded' || realtimeState === 'offline') {
    return POLLING_ENABLED;
  }

  return POLLING_DISABLED;
}
