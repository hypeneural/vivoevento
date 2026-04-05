import type {
  MemoryRuntimeProgress,
  PuzzleRuntimeProgress,
  RuntimeLoadingProgress,
} from '@/modules/play/types';

function toFiniteNumber(value: unknown) {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : null;
}

function toOptionalPhase(value: unknown) {
  const phase = String(value ?? '');
  return phase || undefined;
}

export function parseRuntimeLoadingProgress(
  raw: Record<string, unknown> | null | undefined,
): RuntimeLoadingProgress | null {
  if (!raw || String(raw.phase ?? '') !== 'loading') {
    return null;
  }

  const progress = toFiniteNumber(raw.progress);
  if (progress === null) {
    return null;
  }

  return {
    phase: 'loading',
    progress,
  };
}

export function parseMemoryRuntimeProgress(
  raw: Record<string, unknown> | null | undefined,
): MemoryRuntimeProgress | null {
  if (!raw) {
    return null;
  }

  const moves = toFiniteNumber(raw.moves);
  const mistakes = toFiniteNumber(raw.mistakes);
  const matchedCards = toFiniteNumber(raw.matchedCards ?? raw.matched);
  const matchedPairs = toFiniteNumber(raw.matchedPairs);
  const totalPairs = toFiniteNumber(raw.totalPairs ?? raw.pairsCount);
  const accuracy = toFiniteNumber(raw.accuracy);
  const scorePreview = toFiniteNumber(raw.scorePreview);
  const completionRatio = toFiniteNumber(raw.completionRatio);

  if (
    moves === null
    || mistakes === null
    || matchedCards === null
    || totalPairs === null
  ) {
    return null;
  }

  const derivedMatchedPairs = matchedPairs ?? Math.floor(matchedCards / 2);
  const derivedAccuracy = accuracy ?? (moves > 0 ? (moves - mistakes) / moves : 1);
  const derivedCompletionRatio = completionRatio ?? (totalPairs > 0 ? derivedMatchedPairs / totalPairs : 0);

  if (scorePreview === null) {
    return null;
  }

  return {
    phase: (toOptionalPhase(raw.phase) as MemoryRuntimeProgress['phase']) ?? 'progress',
    moves,
    mistakes,
    matchedCards,
    matchedPairs: derivedMatchedPairs,
    totalPairs,
    accuracy: derivedAccuracy,
    scorePreview,
    completionRatio: derivedCompletionRatio,
  };
}

export function parsePuzzleRuntimeProgress(
  raw: Record<string, unknown> | null | undefined,
): PuzzleRuntimeProgress | null {
  if (!raw) {
    return null;
  }

  const moves = toFiniteNumber(raw.moves);
  const wrongDrops = toFiniteNumber(raw.wrongDrops);
  const placed = toFiniteNumber(raw.placed);
  const total = toFiniteNumber(raw.total);
  const combo = toFiniteNumber(raw.combo);
  const maxCombo = toFiniteNumber(raw.maxCombo);
  const scorePreview = toFiniteNumber(raw.scorePreview);
  const completionRatio = toFiniteNumber(raw.completionRatio);

  if (
    moves === null
    || wrongDrops === null
    || placed === null
    || total === null
    || combo === null
    || maxCombo === null
    || scorePreview === null
    || completionRatio === null
  ) {
    return null;
  }

  return {
    phase: (toOptionalPhase(raw.phase) as PuzzleRuntimeProgress['phase']) ?? 'progress',
    moves,
    wrongDrops,
    placed,
    total,
    combo,
    maxCombo,
    scorePreview,
    completionRatio,
  };
}
