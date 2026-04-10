import { useEffect, useRef, useState } from 'react';

import type { WallRuntimeItem } from '../../types';
import {
  createInitialBoardState,
  reconcileBoardState,
  scheduleBoardBurst,
} from './BoardBurstScheduler';
import type { UseWallBoardOptions, WallBoardState } from './types';

function areSlotsEqual(left: WallBoardState, right: WallBoardState): boolean {
  if (
    left.step !== right.step
    || left.nextPoolOffset !== right.nextPoolOffset
    || left.activeSlotIndexes.length !== right.activeSlotIndexes.length
    || left.slots.length !== right.slots.length
  ) {
    return false;
  }

  for (let index = 0; index < left.activeSlotIndexes.length; index += 1) {
    if (left.activeSlotIndexes[index] !== right.activeSlotIndexes[index]) {
      return false;
    }
  }

  for (let index = 0; index < left.slots.length; index += 1) {
    const leftSlot = left.slots[index];
    const rightSlot = right.slots[index];

    if (
      leftSlot.index !== rightSlot.index
      || leftSlot.enteredAtStep !== rightSlot.enteredAtStep
      || leftSlot.lastUpdatedAtStep !== rightSlot.lastUpdatedAtStep
      || (leftSlot.item?.id ?? null) !== (rightSlot.item?.id ?? null)
    ) {
      return false;
    }
  }

  return true;
}

export function useWallBoard(
  items: WallRuntimeItem[],
  {
    slotCount,
    advanceTrigger,
    boardInstanceKey,
    anchorItemId,
    adjacencyMap,
    maxReplacementsPerBurst,
    avoidSameSender,
    defaultHoldBursts,
    featuredHoldBursts,
  }: UseWallBoardOptions,
) {
  const schedulerOptions = {
    slotCount,
    anchorItemId,
    adjacencyMap,
    maxReplacementsPerBurst,
    avoidSameSender,
    defaultHoldBursts,
    featuredHoldBursts,
  };

  const [boardState, setBoardState] = useState<WallBoardState>(() => createInitialBoardState(items, schedulerOptions));
  const lastBoardInstanceKeyRef = useRef(boardInstanceKey);
  const lastAdvanceTriggerRef = useRef(advanceTrigger);

  useEffect(() => {
    if (boardInstanceKey === lastBoardInstanceKeyRef.current) {
      return;
    }

    lastBoardInstanceKeyRef.current = boardInstanceKey;
    lastAdvanceTriggerRef.current = advanceTrigger;
    setBoardState(createInitialBoardState(items, schedulerOptions));
  }, [advanceTrigger, boardInstanceKey, items, slotCount, anchorItemId, adjacencyMap, maxReplacementsPerBurst, avoidSameSender, defaultHoldBursts, featuredHoldBursts]);

  useEffect(() => {
    setBoardState((previousState) => {
      const nextState = reconcileBoardState(previousState, items);
      return areSlotsEqual(previousState, nextState) ? previousState : nextState;
    });
  }, [items]);

  useEffect(() => {
    if (advanceTrigger === lastAdvanceTriggerRef.current) {
      return;
    }

    lastAdvanceTriggerRef.current = advanceTrigger;
    setBoardState((previousState) => scheduleBoardBurst(previousState, items, schedulerOptions));
  }, [advanceTrigger, items, slotCount, anchorItemId, adjacencyMap, maxReplacementsPerBurst, avoidSameSender, defaultHoldBursts, featuredHoldBursts]);

  return {
    slots: boardState.slots.map((slot) => slot.item),
    slotState: boardState.slots,
    activeSlot: boardState.activeSlotIndexes[0] ?? 0,
    activeSlotIndexes: boardState.activeSlotIndexes,
    boardStep: boardState.step,
  };
}
