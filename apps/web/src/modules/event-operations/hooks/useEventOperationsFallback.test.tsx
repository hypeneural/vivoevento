import { renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { useEventOperationsFallback } from './useEventOperationsFallback';

describe('useEventOperationsFallback', () => {
  it('keeps polling disabled while the live room is connected or recovering actively', () => {
    const connected = renderHook(() => useEventOperationsFallback('connected'));
    const reconnecting = renderHook(() => useEventOperationsFallback('reconnecting'));
    const resyncing = renderHook(() => useEventOperationsFallback('resyncing'));

    expect(connected.result.current.isPollingFallbackActive).toBe(false);
    expect(reconnecting.result.current.isPollingFallbackActive).toBe(false);
    expect(resyncing.result.current.isPollingFallbackActive).toBe(false);
  });

  it('activates light polling only when the room is degraded or offline', () => {
    const degraded = renderHook(() => useEventOperationsFallback('degraded'));
    const offline = renderHook(() => useEventOperationsFallback('offline'));

    expect(degraded.result.current).toEqual({
      isPollingFallbackActive: true,
      roomIntervalMs: 15000,
      timelineIntervalMs: 20000,
    });
    expect(offline.result.current).toEqual({
      isPollingFallbackActive: true,
      roomIntervalMs: 15000,
      timelineIntervalMs: 20000,
    });
  });
});
