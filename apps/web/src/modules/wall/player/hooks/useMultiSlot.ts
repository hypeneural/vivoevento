/**
 * useMultiSlot — Round-robin slot management for multi-item layouts.
 *
 * Instead of modifying the core reducer (single-item engine), multi-item
 * layouts manage their own slot state internally using this hook.
 *
 * Features:
 * - Maintains N visible items from the available pool
 * - Round-robin updates: each advance replaces 1 slot at a time
 * - Avoids showing duplicate items across slots
 * - Handles queue smaller than slot count (empty cells)
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import type { WallRuntimeItem } from '../types';

export interface MultiSlotState {
  /** Current items in each slot (null = empty cell) */
  slots: (WallRuntimeItem | null)[];
  /** Which slot will be updated next */
  nextSlotIndex: number;
}

/**
 * Pick the next item from pool, avoiding items already visible in slots.
 */
function pickNextForSlot(
  pool: WallRuntimeItem[],
  currentSlots: (WallRuntimeItem | null)[],
  poolOffset: number,
): { item: WallRuntimeItem | null; nextOffset: number } {
  const visibleIds = new Set(currentSlots.filter(Boolean).map((s) => s!.id));
  const ready = pool.filter((item) => item.assetStatus === 'ready' && item.url);

  if (ready.length === 0) return { item: null, nextOffset: poolOffset };

  // Try to find an item not already visible
  for (let i = 0; i < ready.length; i++) {
    const idx = (poolOffset + i) % ready.length;
    const candidate = ready[idx];
    if (!visibleIds.has(candidate.id)) {
      return { item: candidate, nextOffset: (poolOffset + i + 1) % ready.length };
    }
  }

  // All visible already — just cycle
  const idx = poolOffset % ready.length;
  return { item: ready[idx], nextOffset: (poolOffset + 1) % ready.length };
}

/**
 * Initialize N slots from the pool.
 */
function initializeSlots(
  pool: WallRuntimeItem[],
  slotCount: number,
): { slots: (WallRuntimeItem | null)[]; offset: number } {
  const slots: (WallRuntimeItem | null)[] = [];
  let offset = 0;

  for (let i = 0; i < slotCount; i++) {
    const { item, nextOffset } = pickNextForSlot(pool, slots, offset);
    slots.push(item);
    offset = nextOffset;
  }

  return { slots, offset };
}

export function useMultiSlot(
  items: WallRuntimeItem[],
  slotCount: number,
  advanceTrigger: number, // increments on each advance from the engine
) {
  const poolOffsetRef = useRef(0);
  const [slotState, setSlotState] = useState<MultiSlotState>(() => {
    const { slots, offset } = initializeSlots(items, slotCount);
    poolOffsetRef.current = offset;
    return { slots, nextSlotIndex: 0 };
  });

  const lastTriggerRef = useRef(advanceTrigger);

  // Re-initialize when items change drastically (e.g. boot/resync)
  const prevItemCountRef = useRef(items.length);
  useEffect(() => {
    if (items.length !== prevItemCountRef.current && items.length > 0) {
      prevItemCountRef.current = items.length;
      const { slots, offset } = initializeSlots(items, slotCount);
      poolOffsetRef.current = offset;
      setSlotState({ slots, nextSlotIndex: 0 });
    }
  }, [items.length, items, slotCount]);

  // Advance one slot on each trigger change
  useEffect(() => {
    if (advanceTrigger === lastTriggerRef.current) return;
    lastTriggerRef.current = advanceTrigger;

    setSlotState((prev) => {
      const { item, nextOffset } = pickNextForSlot(items, prev.slots, poolOffsetRef.current);
      poolOffsetRef.current = nextOffset;

      const newSlots = [...prev.slots];
      newSlots[prev.nextSlotIndex] = item;

      return {
        slots: newSlots,
        nextSlotIndex: (prev.nextSlotIndex + 1) % slotCount,
      };
    });
  }, [advanceTrigger, items, slotCount]);

  return slotState;
}

// Export for testing
export { pickNextForSlot, initializeSlots };
