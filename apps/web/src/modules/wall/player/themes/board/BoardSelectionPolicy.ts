import type { WallRuntimeItem } from '../../types';
import type { WallBoardSlotState } from './types';

export interface BoardCandidateSelectionOptions {
  poolOffset?: number;
  occupiedSlots: WallBoardSlotState[];
  slotIndex?: number;
  avoidSameSender?: boolean;
  reservedIds?: Set<string>;
  reservedSenderKeys?: Set<string>;
}

export function getBoardSenderKey(item: WallRuntimeItem): string {
  return item.senderKey
    ?? item.sender_key
    ?? item.sender_name
    ?? item.id;
}

function getReadyPool(items: WallRuntimeItem[]): WallRuntimeItem[] {
  return items.filter((item) => item.assetStatus === 'ready' && Boolean(item.url));
}

export function pickBoardCandidate(
  items: WallRuntimeItem[],
  {
    poolOffset = 0,
    occupiedSlots,
    slotIndex,
    avoidSameSender = true,
    reservedIds = new Set<string>(),
    reservedSenderKeys = new Set<string>(),
  }: BoardCandidateSelectionOptions,
): { item: WallRuntimeItem | null; nextOffset: number } {
  const ready = getReadyPool(items);

  if (ready.length === 0) {
    return { item: null, nextOffset: poolOffset };
  }

  const visibleIds = new Set<string>();
  const visibleSenderKeys = new Set<string>();

  occupiedSlots.forEach((slot) => {
    if (!slot.item || slot.index === slotIndex) {
      return;
    }

    visibleIds.add(slot.item.id);
    visibleSenderKeys.add(getBoardSenderKey(slot.item));
  });

  let fallback: WallRuntimeItem | null = null;
  let fallbackOffset = poolOffset;

  for (let iteration = 0; iteration < ready.length; iteration += 1) {
    const index = (poolOffset + iteration) % ready.length;
    const candidate = ready[index];
    const senderKey = getBoardSenderKey(candidate);

    if (visibleIds.has(candidate.id) || reservedIds.has(candidate.id)) {
      continue;
    }

    if (
      !fallback
      && !reservedSenderKeys.has(senderKey)
    ) {
      fallback = candidate;
      fallbackOffset = (index + 1) % ready.length;
    }

    if (!avoidSameSender) {
      return {
        item: candidate,
        nextOffset: (index + 1) % ready.length,
      };
    }

    if (visibleSenderKeys.has(senderKey) || reservedSenderKeys.has(senderKey)) {
      continue;
    }

    return {
      item: candidate,
      nextOffset: (index + 1) % ready.length,
    };
  }

  if (fallback) {
    return {
      item: fallback,
      nextOffset: fallbackOffset,
    };
  }

  return { item: null, nextOffset: poolOffset };
}
