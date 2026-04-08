/**
 * Tests for useAdEngine — basic interface/integration tests.
 * Pure ad scheduling logic is tested in adScheduler.test.ts (17 tests).
 */
import { describe, expect, it } from 'vitest';
import { renderHook, act, cleanup } from '@testing-library/react';
import { useAdEngine } from '../hooks/useAdEngine';
import type { WallAdItem } from '../types';

function makeAd(id: number): WallAdItem {
  return {
    id,
    url: `https://cdn.example.com/ad-${id}.jpg`,
    media_type: 'image',
    duration_seconds: 10,
    position: id - 1,
  };
}

describe('useAdEngine', () => {
  it('returns null currentAd when disabled', () => {
    const { result, unmount } = renderHook(() =>
      useAdEngine({
        mode: 'disabled',
        frequency: 5,
        intervalMinutes: 3,
        ads: [makeAd(1)],
        currentItemId: null,
        isPlaying: false,
      }),
    );
    expect(result.current.currentAd).toBeNull();
    unmount();
  });

  it('exposes the correct interface', () => {
    const { result, unmount } = renderHook(() =>
      useAdEngine({
        mode: 'disabled',
        frequency: 5,
        intervalMinutes: 3,
        ads: [],
        currentItemId: null,
        isPlaying: false,
      }),
    );

    expect(result.current.currentAd).toBeNull();
    expect(typeof result.current.onAdFinished).toBe('function');
    expect(typeof result.current.updateAds).toBe('function');
    unmount();
  });

  it('returns null when not playing', () => {
    const { result, unmount } = renderHook(() =>
      useAdEngine({
        mode: 'by_photos',
        frequency: 1,
        intervalMinutes: 3,
        ads: [makeAd(1)],
        currentItemId: 'item_1',
        isPlaying: false,
      }),
    );
    expect(result.current.currentAd).toBeNull();
    unmount();
  });

  it('returns null when ads list is empty', () => {
    const { result, unmount } = renderHook(() =>
      useAdEngine({
        mode: 'by_photos',
        frequency: 5,
        intervalMinutes: 3,
        ads: [],
        currentItemId: null,
        isPlaying: false,
      }),
    );
    expect(result.current.currentAd).toBeNull();
    unmount();
  });

  it('updateAds does not throw', () => {
    const { result, unmount } = renderHook(() =>
      useAdEngine({
        mode: 'by_photos',
        frequency: 5,
        intervalMinutes: 3,
        ads: [],
        currentItemId: null,
        isPlaying: false,
      }),
    );

    act(() => {
      result.current.updateAds([makeAd(10), makeAd(20)]);
    });
    expect(result.current.currentAd).toBeNull();
    unmount();
  });

  it('onAdFinished is safe to call anytime', () => {
    const { result, unmount } = renderHook(() =>
      useAdEngine({
        mode: 'disabled',
        frequency: 5,
        intervalMinutes: 3,
        ads: [],
        currentItemId: null,
        isPlaying: false,
      }),
    );

    act(() => result.current.onAdFinished());
    expect(result.current.currentAd).toBeNull();
    unmount();
  });

  it('triggers ad after N photo advances', () => {
    const ads = [makeAd(1), makeAd(2)];
    let itemId = 'item_0';

    const { result, rerender, unmount } = renderHook(() =>
      useAdEngine({
        mode: 'by_photos',
        frequency: 2,
        intervalMinutes: 3,
        ads,
        currentItemId: itemId,
        isPlaying: true,
      }),
    );

    expect(result.current.currentAd).toBeNull();

    itemId = 'item_1';
    rerender();

    itemId = 'item_2';
    rerender();

    expect(result.current.currentAd).not.toBeNull();
    expect(result.current.currentAd?.id).toBe(1);
    unmount();
  });

  it('clears ad on onAdFinished', () => {
    const ads = [makeAd(1)];
    let itemId = 'item_0';

    const { result, rerender, unmount } = renderHook(() =>
      useAdEngine({
        mode: 'by_photos',
        frequency: 1,
        intervalMinutes: 3,
        ads,
        currentItemId: itemId,
        isPlaying: true,
      }),
    );

    itemId = 'item_1';
    rerender();
    expect(result.current.currentAd).not.toBeNull();

    act(() => result.current.onAdFinished());
    expect(result.current.currentAd).toBeNull();
    unmount();
  });
});
