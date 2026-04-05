import type { MemoryGameSettings } from '@/modules/play/types';

function normalizePairsCount(raw: unknown): MemoryGameSettings['pairsCount'] {
  const value = Number(raw ?? 6);

  if (value >= 10) return 10;
  if (value >= 8) return 8;

  return 6;
}

export function normalizeMemorySettings(raw: Record<string, unknown>): MemoryGameSettings {
  const difficulty = String(raw.difficulty ?? 'normal');

  return {
    pairsCount: normalizePairsCount(raw.pairsCount),
    difficulty: difficulty === 'medium' ? 'normal' : ((difficulty as MemoryGameSettings['difficulty']) || 'normal'),
    showPreviewSeconds: Number(raw.showPreviewSeconds ?? 0),
    allowDuplicateSource: Boolean(raw.allowDuplicateSource ?? false),
    flipBackDelayMs: Number(raw.flipBackDelayMs ?? 800),
    scoringVersion: 'memory_v1',
  };
}
