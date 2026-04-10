import { afterEach, describe, expect, it, vi } from 'vitest';

import { clearWallAssetCaches, primeWallAsset } from './cache';
import type { WallRuntimeItem } from '../types';

function makeVideoItem(overrides: Partial<WallRuntimeItem> = {}): WallRuntimeItem {
  return {
    id: 'media_video_1',
    url: 'https://cdn.example.com/media-video-1.mp4',
    type: 'video',
    sender_name: 'Marina',
    sender_key: 'sender-marina',
    senderKey: 'sender-marina',
    source_type: 'public_upload',
    caption: 'Video da pista',
    duplicate_cluster_key: null,
    duplicateClusterKey: null,
    is_featured: false,
    created_at: '2026-04-08T18:00:00Z',
    assetStatus: 'idle',
    playedAt: null,
    playCount: 0,
    lastError: null,
    width: null,
    height: null,
    orientation: null,
    ...overrides,
  };
}

describe('primeWallAsset', () => {
  afterEach(async () => {
    vi.restoreAllMocks();
    await clearWallAssetCaches();
  });

  it('probes video metadata with a temporary preload=metadata element', async () => {
    const originalCreateElement = document.createElement.bind(document);

    let createdVideo: {
      preload: string;
      muted: boolean;
      videoWidth: number;
      videoHeight: number;
      onloadedmetadata: null | (() => void);
      onerror: null | (() => void);
      removeAttribute: ReturnType<typeof vi.fn>;
      load: ReturnType<typeof vi.fn>;
      _src?: string;
    } | null = null;

    vi.spyOn(document, 'createElement').mockImplementation(((tagName: string) => {
      if (tagName.toLowerCase() !== 'video') {
        return originalCreateElement(tagName);
      }

      createdVideo = {
        preload: '',
        muted: false,
        videoWidth: 1920,
        videoHeight: 1080,
        onloadedmetadata: null,
        onerror: null,
        removeAttribute: vi.fn(),
        load: vi.fn(),
        set src(value: string) {
          this._src = value;
          queueMicrotask(() => {
            this.onloadedmetadata?.();
          });
        },
        get src() {
          return this._src ?? '';
        },
      };

      return createdVideo as unknown as HTMLElement;
    }) as typeof document.createElement);

    const result = await primeWallAsset(makeVideoItem());

    expect(createdVideo).not.toBeNull();
    expect(createdVideo?.preload).toBe('metadata');
    expect(createdVideo?.src).toBe('https://cdn.example.com/media-video-1.mp4');
    expect(createdVideo?.removeAttribute).toHaveBeenCalledWith('src');
    expect(createdVideo?.load).toHaveBeenCalled();
    expect(result).toEqual(expect.objectContaining({
      status: 'ready',
      width: 1920,
      height: 1080,
      orientation: 'horizontal',
      errorMessage: null,
    }));
  });

  it('only reports an image as ready after decode completes in the generic probe pipeline', async () => {
    let settled = false;
    let resolveDecode: (() => void) | null = null;

    const originalImage = globalThis.Image;

    class MockImage {
      onload: null | (() => void) = null;
      onerror: null | (() => void) = null;
      naturalWidth = 1080;
      naturalHeight = 1350;
      decoding: 'sync' | 'async' | 'auto' = 'auto';
      fetchPriority: 'high' | 'low' | 'auto' = 'auto';

      set src(_value: string) {
        queueMicrotask(() => {
          this.onload?.();
        });
      }

      decode() {
        return new Promise<void>((resolve) => {
          resolveDecode = resolve;
        });
      }
    }

    globalThis.Image = MockImage as unknown as typeof Image;

    try {
      const promise = primeWallAsset({
        ...makeVideoItem({
          id: 'media_image_1',
          url: 'https://cdn.example.com/media-image-1.jpg',
          type: 'image',
        }),
      }).then((result) => {
        settled = true;
        return result;
      });

      await Promise.resolve();
      await Promise.resolve();

      expect(settled).toBe(false);

      resolveDecode?.();

      await expect(promise).resolves.toEqual(expect.objectContaining({
        status: 'ready',
        width: 1080,
        height: 1350,
        orientation: 'vertical',
      }));
    } finally {
      globalThis.Image = originalImage;
    }
  });
});
