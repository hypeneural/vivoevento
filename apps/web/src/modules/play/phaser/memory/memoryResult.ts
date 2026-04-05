import type { MemoryGameSettings, NormalizedGameResult } from '@/modules/play/types';

export function buildMemoryResult(params: {
  moves: number;
  mistakes: number;
  elapsedMs: number;
  settings: MemoryGameSettings;
}): NormalizedGameResult {
  const accuracy = params.moves > 0 ? (params.moves - params.mistakes) / params.moves : 1;
  const elapsedSeconds = Math.ceil(params.elapsedMs / 1000);

  return {
    completed: true,
    score: Math.max(0, 1200 - (elapsedSeconds * 6) - (params.moves * 4) - (params.mistakes * 15)),
    timeMs: params.elapsedMs,
    moves: params.moves,
    mistakes: params.mistakes,
    accuracy,
    metadata: {
      pairsCount: params.settings.pairsCount,
      difficulty: params.settings.difficulty,
      scoringVersion: 'memory_v1',
    },
  };
}
