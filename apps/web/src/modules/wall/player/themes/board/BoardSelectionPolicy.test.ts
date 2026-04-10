import { describe, expect, it } from 'vitest';

import { pickBoardCandidate } from './BoardSelectionPolicy';
import type { WallBoardSlotState } from './types';
import type { WallRuntimeItem } from '../../types';

function makeItem(id: string, senderKey: string): WallRuntimeItem {
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
    is_featured: false,
    assetStatus: 'ready',
    playCount: 0,
    width: 1920,
    height: 1080,
    orientation: 'horizontal',
  };
}

function makeSlot(index: number, item: WallRuntimeItem | null): WallBoardSlotState {
  return {
    index,
    item,
    enteredAtStep: 0,
    lastUpdatedAtStep: 0,
  };
}

describe('pickBoardCandidate', () => {
  it('avoids a visible sender when there is an alternative sender available', () => {
    const items = [
      makeItem('a', 'sender-a'),
      makeItem('b', 'sender-b'),
      makeItem('c', 'sender-a'),
      makeItem('d', 'sender-c'),
    ];
    const occupiedSlots = [
      makeSlot(0, items[0]),
      makeSlot(1, items[1]),
    ];

    const { item } = pickBoardCandidate(items, {
      poolOffset: 2,
      occupiedSlots,
      slotIndex: 1,
      avoidSameSender: true,
    });

    expect(item?.id).toBe('d');
  });

  it('allows the same sender when no better alternative exists', () => {
    const items = [
      makeItem('a', 'sender-a'),
      makeItem('b', 'sender-a'),
    ];
    const occupiedSlots = [makeSlot(0, items[0])];

    const { item } = pickBoardCandidate(items, {
      poolOffset: 1,
      occupiedSlots,
      slotIndex: 0,
      avoidSameSender: true,
    });

    expect(item?.id).toBe('b');
  });
});
