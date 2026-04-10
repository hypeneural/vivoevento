import type { WallRuntimeItem } from '../../types';
import { getBoardSenderKey, pickBoardCandidate } from './BoardSelectionPolicy';
import {
  DEFAULT_BOARD_FEATURED_HOLD_BURSTS,
  DEFAULT_BOARD_HOLD_BURSTS,
  DEFAULT_BOARD_MAX_REPLACEMENTS_PER_BURST,
  type WallBoardSchedulerOptions,
  type WallBoardSlotState,
  type WallBoardState,
} from './types';

function cloneSlot(slot: WallBoardSlotState): WallBoardSlotState {
  return {
    index: slot.index,
    item: slot.item,
    enteredAtStep: slot.enteredAtStep,
    lastUpdatedAtStep: slot.lastUpdatedAtStep,
  };
}

function getHoldBursts(slot: WallBoardSlotState, options: WallBoardSchedulerOptions): number {
  if (slot.item?.is_featured) {
    return options.featuredHoldBursts ?? DEFAULT_BOARD_FEATURED_HOLD_BURSTS;
  }

  return options.defaultHoldBursts ?? DEFAULT_BOARD_HOLD_BURSTS;
}

function isSlotEligibleForReplacement(
  slot: WallBoardSlotState,
  nextStep: number,
  options: WallBoardSchedulerOptions,
): boolean {
  if (!slot.item) {
    return true;
  }

  if (options.anchorItemId && slot.item.id === options.anchorItemId) {
    return false;
  }

  return nextStep - slot.lastUpdatedAtStep >= getHoldBursts(slot, options);
}

function isAdjacent(
  leftIndex: number,
  rightIndex: number,
  adjacencyMap: Record<number, number[]>,
): boolean {
  return adjacencyMap[leftIndex]?.includes(rightIndex) || adjacencyMap[rightIndex]?.includes(leftIndex) || false;
}

function selectReplacementIndexes(
  slots: WallBoardSlotState[],
  nextStep: number,
  options: WallBoardSchedulerOptions,
  budget: number,
): number[] {
  const adjacencyMap = options.adjacencyMap ?? {};
  const eligible = slots
    .filter((slot) => slot.item && isSlotEligibleForReplacement(slot, nextStep, options))
    .sort((left, right) => {
      const leftAge = nextStep - left.lastUpdatedAtStep;
      const rightAge = nextStep - right.lastUpdatedAtStep;

      if (leftAge !== rightAge) {
        return rightAge - leftAge;
      }

      return left.index - right.index;
    })
    .map((slot) => slot.index);

  const selected: number[] = [];
  const skipped: number[] = [];

  for (const slotIndex of eligible) {
    if (selected.length >= budget) {
      break;
    }

    if (selected.some((chosenIndex) => isAdjacent(chosenIndex, slotIndex, adjacencyMap))) {
      skipped.push(slotIndex);
      continue;
    }

    selected.push(slotIndex);
  }

  for (const slotIndex of skipped) {
    if (selected.length >= budget) {
      break;
    }

    selected.push(slotIndex);
  }

  return selected;
}

export function reconcileBoardState(
  state: WallBoardState,
  items: WallRuntimeItem[],
): WallBoardState {
  const readyById = new Map(
    items
      .filter((item) => item.assetStatus === 'ready' && item.url)
      .map((item) => [item.id, item] as const),
  );

  let changed = false;
  const slots = state.slots.map((slot) => {
    if (!slot.item) {
      return slot;
    }

    const nextItem = readyById.get(slot.item.id) ?? null;

    if (nextItem === slot.item) {
      return slot;
    }

    changed = true;
    return {
      ...slot,
      item: nextItem,
    };
  });

  if (!changed) {
    return state;
  }

  return {
    ...state,
    slots,
  };
}

export function createInitialBoardState(
  items: WallRuntimeItem[],
  options: WallBoardSchedulerOptions,
): WallBoardState {
  const slots: WallBoardSlotState[] = [];
  let poolOffset = 0;

  for (let index = 0; index < options.slotCount; index += 1) {
    const { item, nextOffset } = pickBoardCandidate(items, {
      poolOffset,
      occupiedSlots: slots,
      slotIndex: index,
      avoidSameSender: options.avoidSameSender ?? true,
    });

    slots.push({
      index,
      item,
      enteredAtStep: item ? 0 : -1,
      lastUpdatedAtStep: item ? 0 : -1,
    });

    poolOffset = nextOffset;
  }

  return {
    slots,
    activeSlotIndexes: [],
    nextPoolOffset: poolOffset,
    step: 0,
  };
}

export function scheduleBoardBurst(
  previousState: WallBoardState,
  items: WallRuntimeItem[],
  options: WallBoardSchedulerOptions,
): WallBoardState {
  const state = reconcileBoardState(previousState, items);
  const nextStep = state.step + 1;
  const maxReplacements = options.maxReplacementsPerBurst ?? DEFAULT_BOARD_MAX_REPLACEMENTS_PER_BURST;
  const slots = state.slots.map(cloneSlot);
  const activeSlotIndexes: number[] = [];
  const reservedIds = new Set<string>();
  const reservedSenderKeys = new Set<string>();
  let nextPoolOffset = state.nextPoolOffset;

  const emptySlotIndexes = slots
    .filter((slot) => !slot.item)
    .map((slot) => slot.index);

  for (const slotIndex of emptySlotIndexes) {
    if (activeSlotIndexes.length >= maxReplacements) {
      break;
    }

    const { item, nextOffset } = pickBoardCandidate(items, {
      poolOffset: nextPoolOffset,
      occupiedSlots: slots,
      slotIndex,
      avoidSameSender: options.avoidSameSender ?? true,
      reservedIds,
      reservedSenderKeys,
    });

    if (!item) {
      continue;
    }

    nextPoolOffset = nextOffset;
    reservedIds.add(item.id);
    reservedSenderKeys.add(getBoardSenderKey(item));
    activeSlotIndexes.push(slotIndex);
    slots[slotIndex] = {
      index: slotIndex,
      item,
      enteredAtStep: nextStep,
      lastUpdatedAtStep: nextStep,
    };
  }

  const remainingBudget = Math.max(0, maxReplacements - activeSlotIndexes.length);
  const replacementIndexes = selectReplacementIndexes(slots, nextStep, options, remainingBudget);

  for (const slotIndex of replacementIndexes) {
    const currentItem = slots[slotIndex]?.item;
    if (!currentItem) {
      continue;
    }

    const reservedIdsForSlot = new Set(reservedIds);
    reservedIdsForSlot.add(currentItem.id);

    const { item, nextOffset } = pickBoardCandidate(items, {
      poolOffset: nextPoolOffset,
      occupiedSlots: slots,
      slotIndex,
      avoidSameSender: options.avoidSameSender ?? true,
      reservedIds: reservedIdsForSlot,
      reservedSenderKeys,
    });

    if (!item || item.id === currentItem.id) {
      continue;
    }

    nextPoolOffset = nextOffset;
    reservedIds.add(item.id);
    reservedSenderKeys.add(getBoardSenderKey(item));
    activeSlotIndexes.push(slotIndex);
    slots[slotIndex] = {
      index: slotIndex,
      item,
      enteredAtStep: nextStep,
      lastUpdatedAtStep: nextStep,
    };
  }

  return {
    slots,
    activeSlotIndexes,
    nextPoolOffset,
    step: nextStep,
  };
}
