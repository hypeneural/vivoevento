/**
 * Tests for useQRFlip hook.
 */
import { describe, expect, it, vi, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useQRFlip } from '../hooks/useQRFlip';

describe('useQRFlip', () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it('starts unflipped', () => {
    const { result } = renderHook(() => useQRFlip({ mode: 'minutes', every: 1, durationSec: 60 }));
    expect(result.current.isFlipped).toBe(false);
  });

  it('does not flip when mode=disabled', () => {
    vi.useFakeTimers();
    const { result } = renderHook(() => useQRFlip({ mode: 'disabled', every: 1, durationSec: 10 }));

    act(() => {
      vi.advanceTimersByTime(120_000);
    });

    expect(result.current.isFlipped).toBe(false);
  });

  it('flips after N minutes in minutes mode', () => {
    vi.useFakeTimers();
    const { result } = renderHook(() => useQRFlip({ mode: 'minutes', every: 1, durationSec: 60 }));

    expect(result.current.isFlipped).toBe(false);

    act(() => {
      vi.advanceTimersByTime(60_100); // 1 minute + 100ms
    });

    expect(result.current.isFlipped).toBe(true);
  });

  it('auto-unflips after durationSec', () => {
    vi.useFakeTimers();
    const { result } = renderHook(() => useQRFlip({ mode: 'minutes', every: 1, durationSec: 10 }));

    act(() => {
      vi.advanceTimersByTime(60_100); // trigger flip
    });

    expect(result.current.isFlipped).toBe(true);

    act(() => {
      vi.advanceTimersByTime(10_100); // wait for duration
    });

    expect(result.current.isFlipped).toBe(false);
  });

  it('flips after N photos in photos mode', () => {
    const { result } = renderHook(() => useQRFlip({ mode: 'photos', every: 3, durationSec: 60 }));

    act(() => result.current.trigger());
    act(() => result.current.trigger());
    expect(result.current.isFlipped).toBe(false);

    act(() => result.current.trigger()); // 3rd photo
    expect(result.current.isFlipped).toBe(true);
  });

  it('resets photo counter after flip', () => {
    vi.useFakeTimers();
    const { result } = renderHook(() => useQRFlip({ mode: 'photos', every: 2, durationSec: 5 }));

    act(() => result.current.trigger());
    act(() => result.current.trigger()); // 2nd = flip
    expect(result.current.isFlipped).toBe(true);

    act(() => {
      vi.advanceTimersByTime(6000); // unflip
    });
    expect(result.current.isFlipped).toBe(false);

    // Counter was reset — need 2 more photos
    act(() => result.current.trigger());
    expect(result.current.isFlipped).toBe(false);
    act(() => result.current.trigger()); // 2nd again = flip again
    expect(result.current.isFlipped).toBe(true);
  });

  it('suppresses flip when qrCentralVisible=true', () => {
    vi.useFakeTimers();
    const { result } = renderHook(() => useQRFlip({
      mode: 'minutes',
      every: 1,
      durationSec: 60,
      qrCentralVisible: true,
    }));

    act(() => {
      vi.advanceTimersByTime(60_100);
    });

    expect(result.current.isFlipped).toBe(false);
  });
});
