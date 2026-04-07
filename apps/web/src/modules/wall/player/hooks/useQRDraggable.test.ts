/**
 * Tests for useQRDraggable hook.
 */
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useQRDraggable } from '../hooks/useQRDraggable';

describe('useQRDraggable', () => {
  beforeEach(() => {
    localStorage.clear();
    // Mock desktop viewport
    Object.defineProperty(window, 'innerWidth', { value: 1920, writable: true, configurable: true });
  });

  it('starts with no position when nothing stored', () => {
    const { result } = renderHook(() => useQRDraggable('TEST123'));
    expect(result.current.position).toBeNull();
    expect(result.current.isDragging).toBe(false);
  });

  it('restores position from localStorage', () => {
    localStorage.setItem('wall-qr-position-TEST123', JSON.stringify({ x: 100, y: 200 }));
    const { result } = renderHook(() => useQRDraggable('TEST123'));
    expect(result.current.position).toEqual({ x: 100, y: 200 });
  });

  it('is inactive when disabled', () => {
    const { result } = renderHook(() => useQRDraggable('TEST123', false));
    expect(result.current.isActive).toBe(false);
  });

  it('is active on desktop when enabled', () => {
    const { result } = renderHook(() => useQRDraggable('TEST123', true));
    expect(result.current.isActive).toBe(true);
  });

  it('is inactive on mobile viewport', () => {
    Object.defineProperty(window, 'innerWidth', { value: 768, writable: true, configurable: true });
    const { result } = renderHook(() => useQRDraggable('TEST123', true));
    expect(result.current.isActive).toBe(false);
  });

  it('provides grab cursor style when active', () => {
    const { result } = renderHook(() => useQRDraggable('TEST123', true));
    expect(result.current.handlers.style?.cursor).toBe('grab');
  });

  it('provides empty style when inactive', () => {
    const { result } = renderHook(() => useQRDraggable('TEST123', false));
    expect(result.current.handlers.style).toEqual({});
  });
});
