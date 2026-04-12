import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useControlRoomLifecycle } from './useControlRoomLifecycle';

describe('useControlRoomLifecycle', () => {
  const originalFullscreenEnabled = Object.getOwnPropertyDescriptor(document, 'fullscreenEnabled');
  const originalFullscreenElement = Object.getOwnPropertyDescriptor(document, 'fullscreenElement');
  const originalVisibilityState = Object.getOwnPropertyDescriptor(document, 'visibilityState');
  const originalRequestFullscreen = Object.getOwnPropertyDescriptor(document.documentElement, 'requestFullscreen');
  const originalWakeLock = Object.getOwnPropertyDescriptor(navigator, 'wakeLock');

  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.unstubAllGlobals();

    if (originalFullscreenEnabled) {
      Object.defineProperty(document, 'fullscreenEnabled', originalFullscreenEnabled);
    }

    if (originalFullscreenElement) {
      Object.defineProperty(document, 'fullscreenElement', originalFullscreenElement);
    }

    if (originalVisibilityState) {
      Object.defineProperty(document, 'visibilityState', originalVisibilityState);
    }

    if (originalRequestFullscreen) {
      Object.defineProperty(document.documentElement, 'requestFullscreen', originalRequestFullscreen);
    }

    if (originalWakeLock) {
      Object.defineProperty(navigator, 'wakeLock', originalWakeLock);
    }
  });

  it('requests fullscreen only when the user action calls the handler', async () => {
    let fullscreenElement: Element | null = null;
    const requestFullscreen = vi.fn().mockImplementation(async () => {
      fullscreenElement = document.documentElement;
      document.dispatchEvent(new Event('fullscreenchange'));
    });

    Object.defineProperty(document, 'fullscreenEnabled', { configurable: true, value: true });
    Object.defineProperty(document, 'fullscreenElement', {
      configurable: true,
      get: () => fullscreenElement,
    });
    Object.defineProperty(document.documentElement, 'requestFullscreen', {
      configurable: true,
      value: requestFullscreen,
    });

    const { result } = renderHook(() => useControlRoomLifecycle({ wakeLockEnabled: false }));

    expect(requestFullscreen).not.toHaveBeenCalled();
    expect(result.current.isFullscreen).toBe(false);

    await act(async () => {
      await result.current.requestFullscreen();
    });

    expect(requestFullscreen).toHaveBeenCalledTimes(1);
    expect(result.current.isFullscreen).toBe(true);
    expect(result.current.fullscreenError).toBeNull();
  });

  it('tracks visibility and reacquires wake lock when the document becomes visible again', async () => {
    let visibilityState: DocumentVisibilityState = 'visible';
    const release = vi.fn().mockResolvedValue(undefined);
    const requestWakeLock = vi.fn().mockResolvedValue({ release });

    Object.defineProperty(document, 'visibilityState', {
      configurable: true,
      get: () => visibilityState,
    });
    Object.defineProperty(navigator, 'wakeLock', {
      configurable: true,
      value: { request: requestWakeLock },
    });

    const { result } = renderHook(() => useControlRoomLifecycle());

    await waitFor(() => expect(result.current.wakeLockStatus).toBe('active'));
    expect(requestWakeLock).toHaveBeenCalledTimes(1);
    expect(result.current.isVisible).toBe(true);

    act(() => {
      visibilityState = 'hidden';
      document.dispatchEvent(new Event('visibilitychange'));
    });

    expect(result.current.isVisible).toBe(false);
    expect(result.current.lifecycleMode).toBe('hidden');

    act(() => {
      visibilityState = 'visible';
      document.dispatchEvent(new Event('visibilitychange'));
    });

    await waitFor(() => expect(requestWakeLock).toHaveBeenCalledTimes(2));
    expect(result.current.isVisible).toBe(true);
  });
});
