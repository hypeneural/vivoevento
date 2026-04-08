/**
 * Tests for useMultiSlot hook and utility functions.
 */
import { describe, expect, it } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useMultiSlot, pickNextForSlot, initializeSlots } from '../hooks/useMultiSlot';
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

describe('pickNextForSlot', () => {
  it('returns item not already in slots', () => {
    const pool = [makeItem('a'), makeItem('b'), makeItem('c')];
    const slots = [makeItem('a'), null, null];
    const { item } = pickNextForSlot(pool, slots, 0);
    expect(item?.id).not.toBe('a');
  });

  it('returns null when pool is empty', () => {
    const { item } = pickNextForSlot([], [null], 0);
    expect(item).toBeNull();
  });

  it('skips non-ready items', () => {
    const pool = [makeItem('a', 'loading'), makeItem('b')];
    const { item } = pickNextForSlot(pool, [null], 0);
    expect(item?.id).toBe('b');
  });
});

describe('initializeSlots', () => {
  it('fills 3 slots with distinct items', () => {
    const pool = [makeItem('a'), makeItem('b'), makeItem('c'), makeItem('d')];
    const { slots } = initializeSlots(pool, 3);
    expect(slots.length).toBe(3);

    const ids = slots.filter(Boolean).map((s) => s!.id);
    expect(new Set(ids).size).toBe(3); // all distinct
  });

  it('fills fewer slots when pool is small', () => {
    const pool = [makeItem('a')];
    const { slots } = initializeSlots(pool, 3);
    // Only 1 ready item, so first slot has it, rest may be null or repeat
    expect(slots.length).toBe(3);
    expect(slots[0]?.id).toBe('a');
  });

  it('returns all null when pool is empty', () => {
    const { slots } = initializeSlots([], 3);
    expect(slots.every((s) => s === null)).toBe(true);
  });
});

describe('useMultiSlot', () => {
  it('initializes with 3 slots from pool', () => {
    const pool = [makeItem('a'), makeItem('b'), makeItem('c'), makeItem('d')];
    const { result } = renderHook(() => useMultiSlot(pool, 3, 0));
    
    expect(result.current.slots.length).toBe(3);
    expect(result.current.nextSlotIndex).toBe(0);
  });

  it('returns empty slots when pool is empty', () => {
    const { result } = renderHook(() => useMultiSlot([], 3, 0));
    expect(result.current.slots.every((s) => s === null)).toBe(true);
  });
});
