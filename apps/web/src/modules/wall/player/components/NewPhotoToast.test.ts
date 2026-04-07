/**
 * Tests for NewPhotoToast hook and message resolution.
 */
import { describe, expect, it, vi, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useNewPhotoToast } from '../components/NewPhotoToast';

// Helper: we export resolveToastMessage for testing via the hook
describe('useNewPhotoToast', () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it('starts with no visible toast and empty message', () => {
    const { result } = renderHook(() => useNewPhotoToast());
    expect(result.current.visible).toBe(false);
    expect(result.current.message).toBe('');
  });

  it('shows toast with sender name when triggered once', () => {
    const { result } = renderHook(() => useNewPhotoToast());

    act(() => {
      result.current.trigger('Maria');
    });

    expect(result.current.visible).toBe(true);
    expect(result.current.message).toBe('📸 Maria enviou uma foto!');
  });

  it('shows batched message when multiple triggers in 5s window', () => {
    const { result } = renderHook(() => useNewPhotoToast());

    act(() => {
      result.current.trigger('Maria');
      result.current.trigger('João');
      result.current.trigger('Ana');
    });

    expect(result.current.visible).toBe(true);
    expect(result.current.message).toBe('📸 3 novas fotos!');
  });

  it('auto-dismisses after 5 seconds', () => {
    vi.useFakeTimers();
    const { result } = renderHook(() => useNewPhotoToast());

    act(() => {
      result.current.trigger('Maria');
    });

    expect(result.current.visible).toBe(true);

    act(() => {
      vi.advanceTimersByTime(5100);
    });

    expect(result.current.visible).toBe(false);
  });

  it('resets dismiss timer when new trigger arrives', () => {
    vi.useFakeTimers();
    const { result } = renderHook(() => useNewPhotoToast());

    act(() => {
      result.current.trigger('Maria');
    });

    // Advance 4 seconds (not yet dismissed)
    act(() => {
      vi.advanceTimersByTime(4000);
    });

    expect(result.current.visible).toBe(true);

    // Trigger again — this resets the 5s timer
    act(() => {
      result.current.trigger('João');
    });

    // Advance 4 more seconds — should still be visible (timer was reset)
    act(() => {
      vi.advanceTimersByTime(4000);
    });

    expect(result.current.visible).toBe(true);
    expect(result.current.message).toBe('📸 2 novas fotos!');
  });
});
