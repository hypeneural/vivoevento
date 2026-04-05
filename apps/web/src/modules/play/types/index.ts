export type NormalizedGameResult = {
  score: number;
  completed: boolean;
  timeMs: number;
  moves: number;
  mistakes?: number;
  accuracy?: number;
  metadata?: Record<string, unknown>;
};

export type PlayerIdentity = {
  identifier: string;
  name?: string | null;
};

export type RestoredGameMove = {
  moveNumber: number;
  type: string;
  payload?: Record<string, unknown>;
  occurredAt?: string;
};

export type GameResumeState = {
  lastAcceptedMoveNumber: number;
  serverElapsedMs: number;
  moves: RestoredGameMove[];
};

export type MemoryGameSettings = {
  pairsCount: number;
  difficulty: 'easy' | 'normal' | 'hard';
  showPreviewSeconds?: number;
  allowDuplicateSource?: boolean;
  flipBackDelayMs?: number;
  scoringVersion?: 'memory_v1';
};

export type PuzzleGameSettings = {
  gridSize: '2x2' | '3x3';
  snapEnabled?: boolean;
  showReferenceImage?: boolean;
  dragTolerance?: number;
  scoringVersion?: 'puzzle_v1';
};

export type RuntimeLoadingProgress = {
  phase: 'loading';
  progress: number;
};

export type MemoryRuntimeProgress = {
  phase?: 'ready' | 'progress' | 'victory';
  moves: number;
  mistakes: number;
  matchedCards: number;
  matchedPairs: number;
  totalPairs: number;
  accuracy: number;
  scorePreview: number;
  completionRatio: number;
};

export type PuzzleRuntimeProgress = {
  phase?: 'ready' | 'progress' | 'victory';
  moves: number;
  wrongDrops: number;
  placed: number;
  total: number;
  combo: number;
  maxCombo: number;
  scorePreview: number;
  completionRatio: number;
};
