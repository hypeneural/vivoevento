import { describe, expect, it } from 'vitest';

import {
  scheduleBoardBurst,
} from './BoardBurstScheduler';
import {
  createLinearAdjacencyMap,
  type WallBoardState,
  type WallBoardSlotState,
} from './types';
import type { WallRuntimeItem } from '../../types';

function makeItem(
  id: string,
  senderKey: string,
  isFeatured = false,
): WallRuntimeItem {
  return {
    id,
    url: `https://cdn.example.com/${id}.jpg`,
    type: 'image',
    sender_name: senderKey,
    sender_key: senderKey,
    senderKey,
    source_type: 'public_upload',
    caption: null,
    duplicate_cluster_key: null,
    duplicateClusterKey: null,
    is_featured: isFeatured,
    assetStatus: 'ready',
    playCount: 0,
    width: 1920,
    height: 1080,
    orientation: 'horizontal',
  };
}

function makeSlot(
  index: number,
  item: WallRuntimeItem | null,
  lastUpdatedAtStep = 0,
): WallBoardSlotState {
  return {
    index,
    item,
    enteredAtStep: item ? lastUpdatedAtStep : -1,
    lastUpdatedAtStep,
  };
}

function makeBoardState(slots: WallBoardSlotState[], nextPoolOffset = 0, step = 0): WallBoardState {
  return {
    slots,
    activeSlotIndexes: [],
    nextPoolOffset,
    step,
  };
}

describe('scheduleBoardBurst', () => {
  it('fills an empty slot before replacing an occupied one', () => {
    const items = [
      makeItem('a', 'sender-a'),
      makeItem('b', 'sender-b'),
      makeItem('c', 'sender-c'),
      makeItem('d', 'sender-d'),
    ];
    const state = makeBoardState([
      makeSlot(0, items[0]),
      makeSlot(1, null, -1),
      makeSlot(2, items[1]),
    ]);

    const next = scheduleBoardBurst(state, items, {
      slotCount: 3,
      maxReplacementsPerBurst: 1,
      adjacencyMap: createLinearAdjacencyMap(3),
    });

    expect(next.activeSlotIndexes).toEqual([1]);
    expect(next.slots[1].item?.id).toBe('c');
    expect(next.slots[0].item?.id).toBe('a');
    expect(next.slots[2].item?.id).toBe('b');
  });

  it('preserves featured items longer than common items', () => {
    const items = [
      makeItem('featured', 'sender-featured', true),
      makeItem('common', 'sender-common'),
      makeItem('next', 'sender-next'),
    ];
    const state = makeBoardState([
      makeSlot(0, items[0], 0),
      makeSlot(1, items[1], 0),
    ]);

    const next = scheduleBoardBurst(state, items, {
      slotCount: 2,
      maxReplacementsPerBurst: 1,
      adjacencyMap: createLinearAdjacencyMap(2),
      defaultHoldBursts: 1,
      featuredHoldBursts: 2,
    });

    expect(next.activeSlotIndexes).toEqual([1]);
    expect(next.slots[0].item?.id).toBe('featured');
    expect(next.slots[1].item?.id).toBe('next');
  });

  it('does not replace the anchor slot while other eligible slots exist', () => {
    const items = [
      makeItem('anchor', 'sender-anchor'),
      makeItem('common', 'sender-common'),
      makeItem('next', 'sender-next'),
    ];
    const state = makeBoardState([
      makeSlot(0, items[0], 0),
      makeSlot(1, items[1], 0),
    ], 2, 2);

    const next = scheduleBoardBurst(state, items, {
      slotCount: 2,
      anchorItemId: 'anchor',
      maxReplacementsPerBurst: 1,
      adjacencyMap: createLinearAdjacencyMap(2),
      defaultHoldBursts: 1,
      featuredHoldBursts: 2,
    });

    expect(next.activeSlotIndexes).toEqual([1]);
    expect(next.slots[0].item?.id).toBe('anchor');
    expect(next.slots[1].item?.id).toBe('next');
  });

  it('avoids replacing adjacent slots in the same burst when there is an alternative', () => {
    const items = [
      makeItem('a', 'sender-a'),
      makeItem('b', 'sender-b'),
      makeItem('c', 'sender-c'),
      makeItem('d', 'sender-d'),
      makeItem('e', 'sender-e'),
      makeItem('f', 'sender-f'),
    ];
    const state = makeBoardState([
      makeSlot(0, items[0], 0),
      makeSlot(1, items[1], 0),
      makeSlot(2, items[2], 0),
      makeSlot(3, items[3], 0),
    ], 4, 3);

    const next = scheduleBoardBurst(state, items, {
      slotCount: 4,
      maxReplacementsPerBurst: 2,
      adjacencyMap: createLinearAdjacencyMap(4),
      defaultHoldBursts: 1,
      featuredHoldBursts: 2,
    });

    expect(next.activeSlotIndexes).toEqual([0, 2]);
    expect(next.slots[0].item?.id).toBe('e');
    expect(next.slots[2].item?.id).toBe('f');
  });
});
