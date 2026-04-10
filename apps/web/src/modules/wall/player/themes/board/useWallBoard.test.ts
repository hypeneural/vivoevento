import { act, renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { createLinearAdjacencyMap } from './types';
import { useWallBoard } from './useWallBoard';
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

describe('useWallBoard', () => {
  it('preserves the current board on incremental queue updates when the board identity is unchanged', () => {
    const initialItems = [
      makeItem('a', 'sender-a'),
      makeItem('b', 'sender-b'),
      makeItem('c', 'sender-c'),
    ];
    const nextItems = [...initialItems, makeItem('d', 'sender-d')];

    const { result, rerender } = renderHook(
      ({ items, boardInstanceKey, advanceTrigger }) => useWallBoard(items, {
        slotCount: 3,
        advanceTrigger,
        boardInstanceKey,
        adjacencyMap: createLinearAdjacencyMap(3),
      }),
      {
        initialProps: {
          items: initialItems,
          boardInstanceKey: 'event:1|layout:grid|preset:standard|tier:premium|rm:0',
          advanceTrigger: 0,
        },
      },
    );

    expect(result.current.slots.map((item) => item?.id ?? null)).toEqual(['a', 'b', 'c']);

    act(() => {
      rerender({
        items: nextItems,
        boardInstanceKey: 'event:1|layout:grid|preset:standard|tier:premium|rm:0',
        advanceTrigger: 0,
      });
    });

    expect(result.current.slots.map((item) => item?.id ?? null)).toEqual(['a', 'b', 'c']);
  });

  it('resets the board when preset or performance tier changes', () => {
    const items = [
      makeItem('a', 'sender-a'),
      makeItem('b', 'sender-b'),
      makeItem('c', 'sender-c'),
      makeItem('d', 'sender-d'),
    ];

    const { result, rerender } = renderHook(
      ({ items: currentItems, boardInstanceKey, advanceTrigger }) => useWallBoard(currentItems, {
        slotCount: 3,
        advanceTrigger,
        boardInstanceKey,
        adjacencyMap: createLinearAdjacencyMap(3),
      }),
      {
        initialProps: {
          items,
          boardInstanceKey: 'event:1|layout:grid|preset:standard|tier:premium|rm:0',
          advanceTrigger: 0,
        },
      },
    );

    act(() => {
      rerender({
        items,
        boardInstanceKey: 'event:1|layout:grid|preset:standard|tier:premium|rm:0',
        advanceTrigger: 1,
      });
    });

    expect(result.current.slots.map((item) => item?.id ?? null)).toEqual(['d', 'b', 'c']);

    act(() => {
      rerender({
        items,
        boardInstanceKey: 'event:1|layout:grid|preset:compact|tier:performance|rm:1',
        advanceTrigger: 1,
      });
    });

    expect(result.current.slots.map((item) => item?.id ?? null)).toEqual(['a', 'b', 'c']);
  });
});
