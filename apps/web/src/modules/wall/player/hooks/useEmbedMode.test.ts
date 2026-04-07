/**
 * Tests for useEmbedMode hook.
 */
import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { renderHook } from '@testing-library/react';
import { useEmbedMode } from '../hooks/useEmbedMode';

function setQueryString(qs: string) {
  Object.defineProperty(window, 'location', {
    value: { ...window.location, search: qs },
    writable: true,
    configurable: true,
  });
}

describe('useEmbedMode', () => {
  const originalLocation = window.location;

  afterEach(() => {
    Object.defineProperty(window, 'location', {
      value: originalLocation,
      writable: true,
      configurable: true,
    });
  });

  it('returns all false when no params', () => {
    setQueryString('');
    const { result } = renderHook(() => useEmbedMode());
    expect(result.current.embedMode).toBe(false);
    expect(result.current.hideQR).toBe(false);
    expect(result.current.hideBranding).toBe(false);
    expect(result.current.hideSideThumbnails).toBe(false);
    expect(result.current.layoutOverride).toBeNull();
    expect(result.current.transitionOverride).toBeNull();
  });

  it('embed=1 activates embed mode and hides QR + branding', () => {
    setQueryString('?embed=1');
    const { result } = renderHook(() => useEmbedMode());
    expect(result.current.embedMode).toBe(true);
    expect(result.current.hideQR).toBe(true);
    expect(result.current.hideBranding).toBe(true);
  });

  it('hideSides=1 hides side thumbnails', () => {
    setQueryString('?hideSides=1');
    const { result } = renderHook(() => useEmbedMode());
    expect(result.current.hideSideThumbnails).toBe(true);
  });

  it('layout=kenburns overrides layout', () => {
    setQueryString('?layout=kenburns');
    const { result } = renderHook(() => useEmbedMode());
    expect(result.current.layoutOverride).toBe('kenburns');
  });

  it('transition=slide overrides transition', () => {
    setQueryString('?transition=slide');
    const { result } = renderHook(() => useEmbedMode());
    expect(result.current.transitionOverride).toBe('slide');
  });
});
