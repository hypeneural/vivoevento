import { renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useReducedControlRoomMotion } from './useReducedControlRoomMotion';

function mockMatchMedia(matches: boolean) {
  vi.stubGlobal('matchMedia', vi.fn().mockImplementation(() => ({
    matches,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
  })));
}

describe('useReducedControlRoomMotion', () => {
  beforeEach(() => {
    vi.unstubAllGlobals();
  });

  it('keeps station gestures semantic when reduced motion is requested', () => {
    mockMatchMedia(true);

    const { result } = renderHook(() => useReducedControlRoomMotion());

    expect(result.current.prefersReducedMotion).toBe(true);
    expect(result.current.motionMode).toBe('reduced');
    expect(result.current.stationGestures.intake).toBe('count_pulse');
    expect(result.current.stationGestures.safety).toBe('color_shift');
    expect(result.current.stationGestures.human_review).toBe('stack_indicator');
    expect(result.current.stationGestures.wall).toBe('current_next_badge');
  });

  it('uses fuller gestures when motion is allowed', () => {
    mockMatchMedia(false);

    const { result } = renderHook(() => useReducedControlRoomMotion());

    expect(result.current.prefersReducedMotion).toBe(false);
    expect(result.current.motionMode).toBe('full');
    expect(result.current.stationGestures.intake).toBe('pulse_and_queue');
    expect(result.current.stationGestures.wall).toBe('monitor_glow');
  });
});
