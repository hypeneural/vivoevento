import { afterEach, describe, expect, it, vi } from 'vitest';

import { resolveWallRuntimeProfile } from './runtime-profile';

describe('resolveWallRuntimeProfile', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('reads device and network hints from the browser when available', () => {
    Object.defineProperty(globalThis, 'navigator', {
      configurable: true,
      value: {
        hardwareConcurrency: 8,
        deviceMemory: 16,
        connection: {
          effectiveType: '4g',
          saveData: false,
          downlink: 22.4,
          rtt: 75,
        },
      },
    });

    Object.defineProperty(document, 'visibilityState', {
      configurable: true,
      value: 'visible',
    });

    vi.spyOn(window, 'matchMedia').mockImplementation((query: string) => ({
      matches: query === '(prefers-reduced-motion: reduce)',
      media: query,
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    }));

    expect(resolveWallRuntimeProfile()).toEqual({
      hardware_concurrency: 8,
      device_memory_gb: 16,
      network_effective_type: '4g',
      network_save_data: false,
      network_downlink_mbps: 22.4,
      network_rtt_ms: 75,
      prefers_reduced_motion: true,
      document_visibility_state: 'visible',
    });
  });

  it('returns null-safe values when the browser does not expose media hints', () => {
    Object.defineProperty(globalThis, 'navigator', {
      configurable: true,
      value: {
        hardwareConcurrency: 0,
      },
    });

    Object.defineProperty(document, 'visibilityState', {
      configurable: true,
      value: 'hidden',
    });

    vi.spyOn(window, 'matchMedia').mockImplementation((query: string) => ({
      matches: false,
      media: query,
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    }));

    expect(resolveWallRuntimeProfile()).toEqual({
      hardware_concurrency: null,
      device_memory_gb: null,
      network_effective_type: null,
      network_save_data: null,
      network_downlink_mbps: null,
      network_rtt_ms: null,
      prefers_reduced_motion: false,
      document_visibility_state: 'hidden',
    });
  });
});
