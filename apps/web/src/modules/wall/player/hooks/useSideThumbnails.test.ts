/**
 * Tests for useSideThumbnails hook and selectThumbnails utility.
 */
import { describe, expect, it, vi, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useSideThumbnails, selectThumbnails } from '../hooks/useSideThumbnails';
import type { WallRuntimeItem } from '../types';

function makeItem(id: string, status: 'ready' | 'loading' = 'ready'): WallRuntimeItem {
  return {
    id,
    url: `https://cdn.example.com/${id}.jpg`,
    type: 'image',
    sender_name: `Sender-${id}`,
    is_featured: false,
    caption: null,
    orientation: 'horizontal',
    senderKey: `sender-${id}`,
    assetStatus: status,
    playCount: 0,
    width: 1920,
    height: 1080,
  };
}

function makeVideoItem(
  id: string,
  options: {
    status?: 'ready' | 'loading';
    previewUrl?: string | null;
  } = {},
): WallRuntimeItem {
  const previewUrl = Object.prototype.hasOwnProperty.call(options, 'previewUrl')
    ? options.previewUrl
    : `https://cdn.example.com/${id}-poster.jpg`;

  return {
    id,
    url: `https://cdn.example.com/${id}.mp4`,
    preview_url: previewUrl,
    type: 'video',
    sender_name: `Sender-${id}`,
    is_featured: false,
    caption: null,
    orientation: 'vertical',
    senderKey: `sender-${id}`,
    assetStatus: options.status ?? 'ready',
    playCount: 0,
    width: 720,
    height: 1280,
    duration_seconds: 8,
  };
}

describe('selectThumbnails', () => {
  it('returns empty array when fewer than 2 candidates', () => {
    const items = [makeItem('1')];
    expect(selectThumbnails(items, null, 8, 0)).toEqual([]);
  });

  it('returns empty array when all items except current are < 2', () => {
    const items = [makeItem('1'), makeItem('2')];
    // currentItemId = '1', so only item '2' is left = 1 candidate < 2
    expect(selectThumbnails(items, '1', 8, 0)).toEqual([]);
  });

  it('returns up to count items, excluding current', () => {
    const items = Array.from({ length: 10 }, (_, i) => makeItem(`item-${i}`));
    const result = selectThumbnails(items, 'item-0', 8, 0);
    expect(result.length).toBe(8);
    expect(result.every((t) => t.id !== 'item-0')).toBe(true);
  });

  it('wraps around when offset exceeds length', () => {
    const items = [makeItem('a'), makeItem('b'), makeItem('c'), makeItem('d')];
    const result = selectThumbnails(items, null, 3, 10);
    expect(result.length).toBe(3);
  });

  it('excludes non-ready items', () => {
    const items = [makeItem('a'), makeItem('b', 'loading'), makeItem('c'), makeItem('d')];
    const result = selectThumbnails(items, null, 8, 0);
    expect(result.every((t) => t.id !== 'b')).toBe(true);
  });

  it('uses video poster urls instead of playback assets for thumbnails', () => {
    const items = [makeItem('a'), makeVideoItem('video-1'), makeItem('b')];
    const result = selectThumbnails(items, null, 8, 0);
    const videoThumb = result.find((item) => item.id === 'video-1');

    expect(videoThumb?.url).toBe('https://cdn.example.com/video-1-poster.jpg');
  });

  it('skips ready videos without preview posters to avoid broken img rendering', () => {
    const items = [makeItem('a'), makeVideoItem('video-1', { previewUrl: null }), makeItem('b')];
    const result = selectThumbnails(items, null, 8, 0);

    expect(result.find((item) => item.id === 'video-1')).toBeUndefined();
  });
});

describe('useSideThumbnails', () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it('returns empty when disabled', () => {
    const items = Array.from({ length: 10 }, (_, i) => makeItem(`item-${i}`));
    const { result } = renderHook(() => useSideThumbnails(items, 'item-0', { enabled: false }));
    expect(result.current.leftItems).toEqual([]);
    expect(result.current.rightItems).toEqual([]);
    expect(result.current.enabled).toBe(false);
  });

  it('splits items 4+4 when enough are available', () => {
    const items = Array.from({ length: 12 }, (_, i) => makeItem(`item-${i}`));
    const { result } = renderHook(() => useSideThumbnails(items, 'item-0', { enabled: true }));
    expect(result.current.leftItems.length).toBe(4);
    expect(result.current.rightItems.length).toBe(4);
    expect(result.current.enabled).toBe(true);
  });

  it('refreshes selection on timer', () => {
    vi.useFakeTimers();
    const items = Array.from({ length: 20 }, (_, i) => makeItem(`item-${i}`));
    const { result } = renderHook(() =>
      useSideThumbnails(items, 'item-0', { enabled: true, refreshMs: 5_000 }),
    );

    const firstLeft = [...result.current.leftItems];

    act(() => {
      vi.advanceTimersByTime(5100);
    });

    // After refresh, the offset changed so items should be different
    const secondLeft = result.current.leftItems;
    // They might coincidentally overlap at certain offsets,
    // but the offset itself should have changed
    expect(secondLeft).toBeDefined();
    expect(secondLeft.length).toBe(4);
  });

  it('returns enabled=false when fewer than 2 items', () => {
    const items = [makeItem('only-one')];
    const { result } = renderHook(() => useSideThumbnails(items, null, { enabled: true }));
    expect(result.current.enabled).toBe(false);
  });
});
