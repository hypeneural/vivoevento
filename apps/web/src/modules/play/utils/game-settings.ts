import type { EventPlaySettings, PlayEventGame } from '@/lib/api-types';

export type GameDraft = {
  title: string;
  slug: string;
  is_active: boolean;
  ranking_enabled: boolean;
  sort_order: number;
  pairsCount: number;
  difficulty: 'easy' | 'normal' | 'hard';
  showPreviewSeconds: number;
  flipBackDelayMs: number;
  allowDuplicateSource: boolean;
  gridSize: '2x2' | '3x3';
  snapEnabled: boolean;
  showReferenceImage: boolean;
  dragTolerance: number;
};

export function mapPuzzleCountToGrid(size: number) {
  if (size >= 9) return '3x3' as const;

  return '2x2' as const;
}

export function normalizeMemoryPairsCount(size: number) {
  if (size >= 10) return 10;
  if (size >= 8) return 8;

  return 6;
}

export function resolveMemoryPairsCount(settings: Record<string, unknown> | null | undefined) {
  const rawValue = Number(settings?.pairsCount ?? settings?.pairs_count ?? 6);
  return normalizeMemoryPairsCount(rawValue);
}

export function resolvePuzzleGridDimension(settings: Record<string, unknown> | null | undefined) {
  const gridSize = String(settings?.gridSize ?? settings?.grid_size ?? '2x2');
  const dimension = Number(gridSize.split('x')[0] ?? 2);

  if (dimension >= 3) {
    return 3;
  }

  return 2;
}

export function buildDefaultGameSettings(
  gameTypeKey: string,
  settings: Pick<EventPlaySettings, 'memory_card_count' | 'puzzle_piece_count'>,
) {
  if (gameTypeKey === 'puzzle') {
    return {
      gridSize: mapPuzzleCountToGrid(settings.puzzle_piece_count),
      snapEnabled: true,
      showReferenceImage: true,
      dragTolerance: 18,
    };
  }

  return {
    pairsCount: normalizeMemoryPairsCount(settings.memory_card_count),
    difficulty: 'normal',
    showPreviewSeconds: 2,
    allowDuplicateSource: false,
    flipBackDelayMs: 800,
  };
}

function normalizeDifficulty(value: unknown): GameDraft['difficulty'] {
  const raw = String(value ?? 'normal');

  if (raw === 'easy' || raw === 'hard' || raw === 'normal') {
    return raw;
  }

  if (raw === 'medium') {
    return 'normal';
  }

  return 'normal';
}

export function createGameDraft(game: PlayEventGame): GameDraft {
  return {
    title: game.title,
    slug: game.slug,
    is_active: game.is_active,
    ranking_enabled: game.ranking_enabled,
    sort_order: game.sort_order,
    pairsCount: normalizeMemoryPairsCount(Number(game.settings.pairsCount ?? 6)),
    difficulty: normalizeDifficulty(game.settings.difficulty),
    showPreviewSeconds: Number(game.settings.showPreviewSeconds ?? 0),
    flipBackDelayMs: Number(game.settings.flipBackDelayMs ?? 800),
    allowDuplicateSource: Boolean(game.settings.allowDuplicateSource ?? false),
    gridSize: String(game.settings.gridSize ?? '3x3') === '2x2' ? '2x2' : '3x3',
    snapEnabled: Boolean(game.settings.snapEnabled ?? true),
    showReferenceImage: Boolean(game.settings.showReferenceImage ?? true),
    dragTolerance: Number(game.settings.dragTolerance ?? 18),
  };
}

export function buildGameDraftSettings(gameTypeKey: string | null, draft: GameDraft) {
  if (gameTypeKey === 'puzzle') {
    return {
      gridSize: draft.gridSize,
      snapEnabled: draft.snapEnabled,
      showReferenceImage: draft.showReferenceImage,
      dragTolerance: draft.dragTolerance,
    };
  }

  return {
    pairsCount: normalizeMemoryPairsCount(draft.pairsCount),
    difficulty: draft.difficulty,
    showPreviewSeconds: draft.showPreviewSeconds,
    allowDuplicateSource: draft.allowDuplicateSource,
    flipBackDelayMs: draft.flipBackDelayMs,
  };
}
