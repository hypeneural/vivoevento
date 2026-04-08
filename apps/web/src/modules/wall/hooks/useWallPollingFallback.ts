import type { WallManagerRealtimeState } from './useWallRealtimeSync';

interface WallPollingFallbackState {
  isPollingFallbackActive: boolean;
  eventIntervalMs: number | false;
  settingsIntervalMs: number | false;
  insightsIntervalMs: number | false;
  diagnosticsIntervalMs: number | false;
}

const POLLING_DISABLED: WallPollingFallbackState = {
  isPollingFallbackActive: false,
  eventIntervalMs: false,
  settingsIntervalMs: false,
  insightsIntervalMs: false,
  diagnosticsIntervalMs: false,
};

const POLLING_ENABLED: WallPollingFallbackState = {
  isPollingFallbackActive: true,
  eventIntervalMs: 30000,
  settingsIntervalMs: 20000,
  insightsIntervalMs: 15000,
  diagnosticsIntervalMs: 10000,
};

export function useWallPollingFallback(realtimeState: WallManagerRealtimeState): WallPollingFallbackState {
  if (realtimeState === 'disconnected' || realtimeState === 'offline') {
    return POLLING_ENABLED;
  }

  return POLLING_DISABLED;
}
