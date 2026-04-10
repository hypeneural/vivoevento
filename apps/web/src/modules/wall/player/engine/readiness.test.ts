import { describe, expect, it } from 'vitest';

import {
  loadWallImageReadiness,
  resolveWallPreloadPlan,
  type WallPreloadPlanEntry,
} from './readiness';
import type { WallRuntimeBudget } from '../runtime-capabilities';
import type { WallRuntimeItem } from '../types';

function makeItem(id: string): WallRuntimeItem {
  return {
    id,
    url: `https://cdn.example.com/${id}.jpg`,
    type: 'image',
    sender_name: `Sender ${id}`,
    sender_key: `sender-${id}`,
    senderKey: `sender-${id}`,
    source_type: 'public_upload',
    caption: null,
    duplicate_cluster_key: null,
    duplicateClusterKey: null,
    is_featured: false,
    assetStatus: 'ready',
    playCount: 0,
    width: 1920,
    height: 1080,
    orientation: 'horizontal',
  };
}

describe('loadWallImageReadiness', () => {
  it('only resolves as ready after decode finishes', async () => {
    let settled = false;
    let resolveDecode: (() => void) | null = null;

    class MockImage {
      onload: null | (() => void) = null;
      onerror: null | (() => void) = null;
      naturalWidth = 1920;
      naturalHeight = 1080;
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

    const originalImage = globalThis.Image;
    globalThis.Image = MockImage as unknown as typeof Image;

    try {
      const promise = loadWallImageReadiness('https://cdn.example.com/media.jpg').then((result) => {
        settled = true;
        return result;
      });

      await Promise.resolve();
      await Promise.resolve();

      expect(settled).toBe(false);

      resolveDecode?.();

      await expect(promise).resolves.toEqual(expect.objectContaining({
        status: 'ready',
        networkReady: true,
        decodeReady: true,
        width: 1920,
        height: 1080,
      }));
    } finally {
      globalThis.Image = originalImage;
    }
  });
});

describe('resolveWallPreloadPlan', () => {
  it('keeps the warm window bounded and reserves high priority for the next burst', () => {
    const budget: WallRuntimeBudget = {
      performanceTier: 'performance',
      maxBoardPieces: 6,
      maxConcurrentDecode: 1,
      maxBurstItems: 1,
      maxStrongAnimations: 1,
    };
    const items = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h']
      .map((id) => makeItem(id));

    const plan = resolveWallPreloadPlan({
      items,
      currentItem: items[0],
      layout: 'puzzle',
      budget,
    });

    expect(plan.map((entry) => entry.item.id)).toEqual(['a', 'b', 'c', 'd', 'e', 'f', 'g']);
    expect(plan.find((entry) => entry.item.id === 'g')).toEqual(expect.objectContaining<Partial<WallPreloadPlanEntry>>({
      reason: 'next-burst',
      fetchPriority: 'high',
    }));
    expect(plan.some((entry) => entry.item.id === 'h')).toBe(false);
  });
});
