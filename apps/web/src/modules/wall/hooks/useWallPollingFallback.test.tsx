import { renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { useWallPollingFallback } from './useWallPollingFallback';

describe('useWallPollingFallback', () => {
  it('desliga o polling quando o realtime esta conectado', () => {
    const { result } = renderHook(() => useWallPollingFallback('connected'));

    expect(result.current.isPollingFallbackActive).toBe(false);
    expect(result.current.eventIntervalMs).toBe(false);
    expect(result.current.settingsIntervalMs).toBe(false);
    expect(result.current.insightsIntervalMs).toBe(false);
    expect(result.current.liveSnapshotIntervalMs).toBe(false);
    expect(result.current.diagnosticsIntervalMs).toBe(false);
  });

  it('mantem o polling desligado enquanto ainda esta conectando', () => {
    const { result } = renderHook(() => useWallPollingFallback('connecting'));

    expect(result.current.isPollingFallbackActive).toBe(false);
    expect(result.current.eventIntervalMs).toBe(false);
    expect(result.current.settingsIntervalMs).toBe(false);
    expect(result.current.insightsIntervalMs).toBe(false);
    expect(result.current.liveSnapshotIntervalMs).toBe(false);
    expect(result.current.diagnosticsIntervalMs).toBe(false);
  });

  it('ativa polling leve quando o canal cai', () => {
    const { result } = renderHook(() => useWallPollingFallback('disconnected'));

    expect(result.current.isPollingFallbackActive).toBe(true);
    expect(result.current.eventIntervalMs).toBe(30000);
    expect(result.current.settingsIntervalMs).toBe(20000);
    expect(result.current.insightsIntervalMs).toBe(15000);
    expect(result.current.liveSnapshotIntervalMs).toBe(5000);
    expect(result.current.diagnosticsIntervalMs).toBe(10000);
  });

  it('ativa polling leve quando o realtime fica offline', () => {
    const { result } = renderHook(() => useWallPollingFallback('offline'));

    expect(result.current.isPollingFallbackActive).toBe(true);
    expect(result.current.eventIntervalMs).toBe(30000);
    expect(result.current.settingsIntervalMs).toBe(20000);
    expect(result.current.insightsIntervalMs).toBe(15000);
    expect(result.current.liveSnapshotIntervalMs).toBe(5000);
    expect(result.current.diagnosticsIntervalMs).toBe(10000);
  });
});
