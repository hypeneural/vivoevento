import type { PlayRestoreMove, StartPlaySessionResponse } from '@/lib/api-types';

import { resolveMemoryPairsCount, resolvePuzzleGridDimension } from './game-settings';

function toStringValue(value: unknown) {
  if (value === null || value === undefined) {
    return null;
  }

  const normalized = String(value);
  return normalized || null;
}

function buildInitialMemoryProgress(settings: Record<string, unknown>) {
  const totalPairs = resolveMemoryPairsCount(settings);

  return {
    phase: 'ready',
    moves: 0,
    mistakes: 0,
    matchedCards: 0,
    matchedPairs: 0,
    totalPairs,
    accuracy: 1,
    scorePreview: 1200,
    completionRatio: 0,
  };
}

function buildInitialPuzzleProgress(settings: Record<string, unknown>) {
  const grid = resolvePuzzleGridDimension(settings);
  const total = grid * grid;

  return {
    phase: 'ready',
    moves: 0,
    wrongDrops: 0,
    placed: 0,
    total,
    combo: 0,
    maxCombo: 0,
    scorePreview: 1200,
    completionRatio: 0,
  };
}

function buildMemoryScorePreview(moves: number, mistakes: number, elapsedMs: number) {
  const elapsedSeconds = Math.ceil(Math.max(elapsedMs, 0) / 1000);
  return Math.max(0, 1200 - (elapsedSeconds * 6) - (moves * 4) - (mistakes * 15));
}

function buildPuzzleScorePreview(moves: number, wrongDrops: number, elapsedMs: number) {
  const elapsedSeconds = Math.ceil(Math.max(elapsedMs, 0) / 1000);
  return Math.max(0, 1200 - (elapsedSeconds * 5) - (moves * 2) - (wrongDrops * 8));
}

function deriveMemoryRestoreProgress(
  settings: Record<string, unknown>,
  moves: PlayRestoreMove[],
  elapsedMs: number,
) {
  const totalPairs = resolveMemoryPairsCount(settings);
  const matchedAssetIds = new Set(
    moves
      .filter((move) => move.type === 'match')
      .map((move) => toStringValue(move.payload?.assetId ?? move.payload?.asset_id))
      .filter((value): value is string => Boolean(value)),
  );
  const resolvedMoves = moves.filter((move) => move.type === 'match' || move.type === 'mismatch').length;
  const mistakes = moves.filter((move) => move.type === 'mismatch').length;
  const matchedPairs = matchedAssetIds.size;

  return {
    phase: matchedPairs >= totalPairs ? 'victory' : 'progress',
    moves: resolvedMoves,
    mistakes,
    matchedCards: matchedPairs * 2,
    matchedPairs,
    totalPairs,
    accuracy: resolvedMoves > 0 ? (resolvedMoves - mistakes) / resolvedMoves : 1,
    scorePreview: buildMemoryScorePreview(resolvedMoves, mistakes, elapsedMs),
    completionRatio: totalPairs > 0 ? matchedPairs / totalPairs : 0,
  };
}

function derivePuzzleRestoreProgress(
  settings: Record<string, unknown>,
  moves: PlayRestoreMove[],
  elapsedMs: number,
) {
  const grid = resolvePuzzleGridDimension(settings);
  const total = grid * grid;
  const placedPieceKeys = new Set<string>();
  let resolvedMoves = 0;
  let wrongDrops = 0;
  let combo = 0;
  let maxCombo = 0;

  for (const move of moves) {
    if (move.type === 'drop') {
      resolvedMoves += 1;

      if (move.payload?.snapped === false) {
        wrongDrops += 1;
        combo = 0;
      }
    }

    if (move.type === 'complete_piece') {
      const pieceKey = toStringValue(move.payload?.pieceKey ?? move.payload?.piece_key ?? move.moveNumber);
      if (pieceKey) {
        placedPieceKeys.add(pieceKey);
      }

      combo += 1;
      if (combo > maxCombo) {
        maxCombo = combo;
      }
    }
  }

  const placed = placedPieceKeys.size;

  return {
    phase: placed >= total ? 'victory' : 'progress',
    moves: resolvedMoves,
    wrongDrops,
    placed,
    total,
    combo,
    maxCombo,
    scorePreview: buildPuzzleScorePreview(resolvedMoves, wrongDrops, elapsedMs),
    completionRatio: total > 0 ? placed / total : 0,
  };
}

export function buildInitialRuntimeProgress(gameKey: string | null | undefined, settings: Record<string, unknown>) {
  if (gameKey === 'memory') {
    return buildInitialMemoryProgress(settings);
  }

  if (gameKey === 'puzzle') {
    return buildInitialPuzzleProgress(settings);
  }

  return {};
}

export function buildRestoredRuntimeProgress(response: StartPlaySessionResponse) {
  if (!response.restore) {
    return buildInitialRuntimeProgress(response.gameKey, response.settings);
  }

  if (response.gameKey === 'memory') {
    return deriveMemoryRestoreProgress(
      response.settings,
      response.restore.moves,
      response.restore.serverElapsedMs,
    );
  }

  if (response.gameKey === 'puzzle') {
    return derivePuzzleRestoreProgress(
      response.settings,
      response.restore.moves,
      response.restore.serverElapsedMs,
    );
  }

  return {};
}
