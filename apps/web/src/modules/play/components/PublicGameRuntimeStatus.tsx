import type { MemoryRuntimeProgress, PuzzleRuntimeProgress } from '@/modules/play/types';

import { PublicGameMemoryStatus } from './PublicGameMemoryStatus';
import { PublicGamePuzzleStatus } from './PublicGamePuzzleStatus';

type PublicGameRuntimeStatusProps = {
  gameKey: string | null;
  memoryProgress: MemoryRuntimeProgress | null;
  puzzleProgress: PuzzleRuntimeProgress | null;
};

export function PublicGameRuntimeStatus({
  gameKey,
  memoryProgress,
  puzzleProgress,
}: PublicGameRuntimeStatusProps) {
  if (gameKey === 'memory' && memoryProgress) {
    return <PublicGameMemoryStatus progress={memoryProgress} />;
  }

  if (gameKey === 'puzzle' && puzzleProgress) {
    return <PublicGamePuzzleStatus progress={puzzleProgress} />;
  }

  return null;
}
