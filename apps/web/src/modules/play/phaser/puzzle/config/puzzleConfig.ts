import type { PuzzleGameSettings } from '@/modules/play/types';

import type { PuzzleGridSizeKey, PuzzleGridSpec } from '../types/puzzle.types';

export function normalizePuzzleSettings(raw: Record<string, unknown>): PuzzleGameSettings {
  const gridSize = String(raw.gridSize ?? '3x3') as PuzzleGridSizeKey;

  return {
    gridSize: gridSize === '2x2' ? '2x2' : '3x3',
    snapEnabled: Boolean(raw.snapEnabled ?? true),
    showReferenceImage: Boolean(raw.showReferenceImage ?? true),
    dragTolerance: Number(raw.dragTolerance ?? 18),
    scoringVersion: 'puzzle_v1',
  };
}

export function resolvePuzzleGrid(gridSize: string): PuzzleGridSpec {
  if (gridSize === '2x2') {
    return {
      key: '2x2',
      rows: 2,
      cols: 2,
    };
  }

  return {
    key: '3x3',
    rows: 3,
    cols: 3,
  };
}
