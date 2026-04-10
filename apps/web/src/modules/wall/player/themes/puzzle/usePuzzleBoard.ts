import { useMemo } from 'react';

import type { WallRuntimeItem, WallSettings } from '../../types';
import { useWallBoard } from '../board/useWallBoard';

interface UsePuzzleBoardOptions {
  settings: WallSettings;
  boardInstanceKey: string;
  advanceTrigger: number;
  maxBoardPieces: number;
  reducedMotion: boolean;
}

export function resolvePuzzlePieceCount(
  preset: WallSettings['theme_config']['preset'] | undefined,
  maxBoardPieces: number,
): number {
  const preferredCount = preset === 'compact' ? 6 : 9;
  return Math.min(preferredCount, maxBoardPieces);
}

export function resolvePuzzleAnchorIndex(pieceCount: number): number {
  return pieceCount === 6 ? 2 : 4;
}

export function usePuzzleBoard(
  items: WallRuntimeItem[],
  {
    settings,
    boardInstanceKey,
    advanceTrigger,
    maxBoardPieces,
    reducedMotion,
  }: UsePuzzleBoardOptions,
) {
  const preset = settings.theme_config?.preset ?? 'standard';
  const anchorMode = settings.theme_config?.anchor_mode ?? 'none';
  const anchorEnabled = anchorMode !== 'none';
  const pieceCount = resolvePuzzlePieceCount(preset, maxBoardPieces);
  const anchorIndex = resolvePuzzleAnchorIndex(pieceCount);
  const imageItems = useMemo(
    () => items.filter((item) => item.type === 'image'),
    [items],
  );

  const board = useWallBoard(imageItems, {
    slotCount: anchorEnabled ? Math.max(1, pieceCount - 1) : pieceCount,
    advanceTrigger,
    boardInstanceKey: `${boardInstanceKey}|anchor:${anchorMode}|pieces:${pieceCount}|rm:${reducedMotion ? 1 : 0}`,
  });

  return {
    ...board,
    pieceCount,
    anchorEnabled,
    anchorIndex,
  };
}

export default usePuzzleBoard;
