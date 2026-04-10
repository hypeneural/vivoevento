import type { WallLayout, WallRuntimeItem } from '../../types';

export const DEFAULT_BOARD_MAX_REPLACEMENTS_PER_BURST = 1;
export const DEFAULT_BOARD_HOLD_BURSTS = 1;
export const DEFAULT_BOARD_FEATURED_HOLD_BURSTS = 2;

export interface WallBoardSlotState {
  index: number;
  item: WallRuntimeItem | null;
  enteredAtStep: number;
  lastUpdatedAtStep: number;
}

export interface WallBoardState {
  slots: WallBoardSlotState[];
  activeSlotIndexes: number[];
  nextPoolOffset: number;
  step: number;
}

export interface WallBoardIdentity {
  eventId?: string | number | null;
  layout: WallLayout;
  preset?: string | null;
  themeVersion: string;
  performanceTier: string;
  reducedMotion: boolean;
}

export interface WallBoardSchedulerOptions {
  slotCount: number;
  anchorItemId?: string | null;
  adjacencyMap?: Record<number, number[]>;
  maxReplacementsPerBurst?: number;
  avoidSameSender?: boolean;
  defaultHoldBursts?: number;
  featuredHoldBursts?: number;
}

export interface UseWallBoardOptions extends WallBoardSchedulerOptions {
  advanceTrigger: number;
  boardInstanceKey: string;
}

export function createBoardInstanceKey({
  eventId,
  layout,
  preset,
  themeVersion,
  performanceTier,
  reducedMotion,
}: WallBoardIdentity): string {
  return [
    `event:${eventId ?? 'unknown'}`,
    `layout:${layout}`,
    `preset:${preset ?? 'default'}`,
    `theme:${themeVersion}`,
    `tier:${performanceTier}`,
    `rm:${reducedMotion ? 1 : 0}`,
  ].join('|');
}

export function createLinearAdjacencyMap(slotCount: number): Record<number, number[]> {
  return Array.from({ length: slotCount }).reduce<Record<number, number[]>>((acc, _, index) => {
    const neighbors: number[] = [];

    if (index > 0) {
      neighbors.push(index - 1);
    }

    if (index < slotCount - 1) {
      neighbors.push(index + 1);
    }

    acc[index] = neighbors;
    return acc;
  }, {});
}
