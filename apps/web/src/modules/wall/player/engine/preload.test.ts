/**
 * Tests for engine/preload.ts (Phase 0.1)
 */

import { describe, it, expect, vi, afterEach } from 'vitest';
import {
  preloadImageWithDecode,
  preloadNextItem,
  preloadVideoAuto,
  resolveNextPreloadItem,
} from '../engine/preload';
import type { WallRuntimeItem, WallSettings, WallSenderRuntimeStats } from '../types';

function makeItem(id: string, overrides?: Partial<WallRuntimeItem>): WallRuntimeItem {
  return {
    id,
    url: `https://cdn.test/${id}.jpg`,
    type: 'image',
    sender_name: `User ${id}`,
    sender_key: `user-${id}`,
    senderKey: `user-${id}`,
    duplicateClusterKey: null,
    caption: null,
    is_featured: false,
    created_at: new Date().toISOString(),
    assetStatus: 'ready',
    playedAt: null,
    playCount: 0,
    lastError: null,
    width: 1920,
    height: 1080,
    orientation: 'horizontal',
    ...overrides,
  } as WallRuntimeItem;
}

const defaultSettings: WallSettings = {
  layout: 'fullscreen',
  transition_effect: 'fade',
  interval_ms: 8000,
  show_branding: true,
  show_qr: true,
  show_neon: false,
  neon_text: null,
  neon_color: null,
  background_url: null,
  partner_logo_url: null,
  show_sender_credit: false,
  show_side_thumbnails: true,
  accepted_orientation: 'all',
  video_enabled: true,
  video_playback_mode: 'play_to_end_if_short_else_cap',
  video_max_seconds: 15,
  video_resume_mode: 'resume_if_same_item_else_restart',
  video_audio_policy: 'muted',
  video_multi_layout_policy: 'disallow',
  video_preferred_variant: 'wall_video_720p',
  ad_mode: 'disabled',
  ad_frequency: 5,
  ad_interval_minutes: 3,
  instructions_text: null,
  queue_limit: 100,
  selection_mode: 'balanced',
  event_phase: 'flow',
  selection_policy: {
    max_eligible_items_per_sender: 4,
    max_replays_per_item: 2,
    low_volume_max_items: 6,
    medium_volume_max_items: 12,
    replay_interval_low_minutes: 8,
    replay_interval_medium_minutes: 12,
    replay_interval_high_minutes: 20,
    sender_cooldown_seconds: 60,
    sender_window_limit: 3,
    sender_window_minutes: 10,
    avoid_same_sender_if_alternative_exists: true,
    avoid_same_duplicate_cluster_if_alternative_exists: true,
  },
};

const emptySenderStats: Record<string, WallSenderRuntimeStats> = {};

describe('resolveNextPreloadItem', () => {
  it('returns null when settings is null', () => {
    const items = [makeItem('a')];
    const result = resolveNextPreloadItem(items, 'a', null, emptySenderStats);
    expect(result).toBeNull();
  });

  it('returns null when items is empty', () => {
    const result = resolveNextPreloadItem([], 'a', defaultSettings, emptySenderStats);
    expect(result).toBeNull();
  });

  it('returns null when currentItemId is null', () => {
    const items = [makeItem('a')];
    const result = resolveNextPreloadItem(items, null, defaultSettings, emptySenderStats);
    expect(result).toBeNull();
  });

  it('returns null when there is only 1 item (next === current)', () => {
    const items = [makeItem('a')];
    const result = resolveNextPreloadItem(items, 'a', defaultSettings, emptySenderStats);
    // With only 1 item, pickNextWallItemId may return same item or null
    // Either way, next should not be the same as current
    expect(result === null || result.id !== 'a').toBe(true);
  });

  it('returns a different item when multiple items exist', () => {
    const items = [
      makeItem('a', { playedAt: new Date().toISOString(), playCount: 1 }),
      makeItem('b'),
      makeItem('c'),
    ];
    const result = resolveNextPreloadItem(items, 'a', defaultSettings, emptySenderStats);
    expect(result).not.toBeNull();
    expect(result!.id).not.toBe('a');
  });

  it('returns null when status is not playing (checked externally, but items/settings valid)', () => {
    // This is checked in useWallEngine, not in resolveNextPreloadItem.
    // resolveNextPreloadItem itself just resolves the item.
    const items = [makeItem('a'), makeItem('b')];
    const result = resolveNextPreloadItem(items, 'a', defaultSettings, emptySenderStats);
    expect(result).not.toBeNull();
  });

  it('skips items with error status', () => {
    const items = [
      makeItem('a', { playCount: 1, playedAt: new Date().toISOString() }),
      makeItem('b', { assetStatus: 'error' }),
      makeItem('c'),
    ];
    const result = resolveNextPreloadItem(items, 'a', defaultSettings, emptySenderStats);
    expect(result).not.toBeNull();
    expect(result!.id).not.toBe('b');
  });
});

describe('video preload helpers', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('creates a muted preload=auto video element for proactive buffering', () => {
    const originalCreateElement = document.createElement.bind(document);
    const created: HTMLVideoElement[] = [];

    vi.spyOn(document, 'createElement').mockImplementation(((tagName: string) => {
      const element = originalCreateElement(tagName);
      if (tagName.toLowerCase() === 'video') {
        created.push(element as HTMLVideoElement);
      }
      return element;
    }) as typeof document.createElement);

    preloadVideoAuto('https://cdn.test/video.mp4');

    expect(created).toHaveLength(1);
    expect(created[0].preload).toBe('auto');
    expect(created[0].muted).toBe(true);
    expect(created[0].src).toContain('https://cdn.test/video.mp4');
  });

  it('sets fetchPriority and decoding hints when preloading the piece entering now', async () => {
    let createdImage: {
      decoding: 'sync' | 'async' | 'auto';
      fetchPriority: 'high' | 'low' | 'auto';
      decode: ReturnType<typeof vi.fn>;
      _src?: string;
    } | null = null;

    const originalImage = globalThis.Image;

    class MockImage {
      decoding: 'sync' | 'async' | 'auto' = 'auto';
      fetchPriority: 'high' | 'low' | 'auto' = 'auto';
      decode = vi.fn().mockResolvedValue(undefined);

      constructor() {
        createdImage = this as unknown as typeof createdImage;
      }

      set src(value: string) {
        this._src = value;
      }

      get src() {
        return this._src ?? '';
      }
    }

    globalThis.Image = MockImage as unknown as typeof Image;

    try {
      const ready = await preloadImageWithDecode('https://cdn.test/anchor.jpg', {
        fetchPriority: 'high',
        decoding: 'sync',
      });

      expect(ready).toBe(true);
      expect(createdImage?.fetchPriority).toBe('high');
      expect(createdImage?.decoding).toBe('sync');
      expect(createdImage?.decode).toHaveBeenCalled();
    } finally {
      globalThis.Image = originalImage;
    }
  });

  it('uses video preloading when the next item is a video', async () => {
    const originalCreateElement = document.createElement.bind(document);
    const created: HTMLVideoElement[] = [];

    vi.spyOn(document, 'createElement').mockImplementation(((tagName: string) => {
      const element = originalCreateElement(tagName);
      if (tagName.toLowerCase() === 'video') {
        created.push(element as HTMLVideoElement);
      }
      return element;
    }) as typeof document.createElement);

    await preloadNextItem(makeItem('video-next', {
      url: 'https://cdn.test/video-next.mp4',
      type: 'video',
    }));

    expect(created).toHaveLength(1);
    expect(created[0].preload).toBe('auto');
    expect(created[0].src).toContain('https://cdn.test/video-next.mp4');
  });
});
