import { renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { usePerformanceMode } from './usePerformanceMode';

function mockMatchMedia(matches: boolean) {
  vi.stubGlobal('matchMedia', vi.fn().mockImplementation(() => ({
    matches,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
  })));
}

describe('usePerformanceMode', () => {
  const originalDeviceMemory = Object.getOwnPropertyDescriptor(navigator, 'deviceMemory');
  const originalHardwareConcurrency = Object.getOwnPropertyDescriptor(navigator, 'hardwareConcurrency');

  beforeEach(() => {
    vi.unstubAllGlobals();
  });

  afterEach(() => {
    if (originalDeviceMemory) {
      Object.defineProperty(navigator, 'deviceMemory', originalDeviceMemory);
    }

    if (originalHardwareConcurrency) {
      Object.defineProperty(navigator, 'hardwareConcurrency', originalHardwareConcurrency);
    }
  });

  it('returns the performance runtime budget on low-end hardware', () => {
    mockMatchMedia(false);
    Object.defineProperty(navigator, 'deviceMemory', {
      configurable: true,
      value: 4,
    });
    Object.defineProperty(navigator, 'hardwareConcurrency', {
      configurable: true,
      value: 4,
    });

    const { result } = renderHook(() => usePerformanceMode());

    expect(result.current.reducedEffects).toBe(true);
    expect(result.current.performanceTier).toBe('performance');
    expect(result.current.runtimeBudget).toEqual(expect.objectContaining({
      maxBoardPieces: 6,
      maxConcurrentDecode: 1,
      maxBurstItems: 1,
      maxStrongAnimations: 1,
    }));
  });

  it('returns the premium runtime budget on stronger hardware', () => {
    mockMatchMedia(false);
    Object.defineProperty(navigator, 'deviceMemory', {
      configurable: true,
      value: 8,
    });
    Object.defineProperty(navigator, 'hardwareConcurrency', {
      configurable: true,
      value: 8,
    });

    const { result } = renderHook(() => usePerformanceMode());

    expect(result.current.reducedEffects).toBe(false);
    expect(result.current.performanceTier).toBe('premium');
    expect(result.current.runtimeBudget).toEqual(expect.objectContaining({
      maxBoardPieces: 9,
      maxConcurrentDecode: 2,
      maxBurstItems: 2,
      maxStrongAnimations: 2,
    }));
  });
});
