import type Phaser from 'phaser';

import type { SceneViewport } from '../../core/viewport';

export type PuzzleGridSizeKey = '2x2' | '3x3';

export type PuzzleGridSpec = {
  key: PuzzleGridSizeKey;
  rows: number;
  cols: number;
};

export type PuzzleSlotVisualState = 'idle' | 'available' | 'invalid' | 'locked';

export type PuzzleSceneLayout = {
  viewport: SceneViewport;
  boardRect: { x: number; y: number; w: number; h: number };
  trayRect: { x: number; y: number; w: number; h: number };
  boardX: number;
  boardY: number;
  boardSize: number;
  pieceWidth: number;
  pieceHeight: number;
  trayColumns: number;
  trayRows: number;
  trayGap: number;
  trayPieceWidth: number;
  trayPieceHeight: number;
};

export type PuzzleSlotModel = {
  id: string;
  row: number;
  col: number;
  textureKey: string;
  x: number;
  y: number;
  width: number;
  height: number;
  zone: Phaser.GameObjects.Zone;
  frame: Phaser.GameObjects.Rectangle;
  highlight: Phaser.GameObjects.Rectangle;
  state: PuzzleSlotVisualState;
  placedPieceId: string | null;
};

export type PuzzlePieceModel = {
  id: string;
  slotId: string;
  textureKey: string;
  sprite: Phaser.GameObjects.Image;
  homeX: number;
  homeY: number;
  homeScaleX: number;
  homeScaleY: number;
  targetX: number;
  targetY: number;
  boardScaleX: number;
  boardScaleY: number;
  isLocked: boolean;
  currentSlotId: string | null;
  activeSlotId: string | null;
  dropSlotId: string | null;
};

export type PuzzleBoardState = {
  grid: PuzzleGridSpec;
  layout: PuzzleSceneLayout;
  slots: PuzzleSlotModel[];
  pieces: PuzzlePieceModel[];
  slotsById: Map<string, PuzzleSlotModel>;
  piecesById: Map<string, PuzzlePieceModel>;
  placedCount: number;
  totalPieces: number;
};

export type PuzzleComboSnapshot = {
  current: number;
  max: number;
  isHot: boolean;
};

export type PuzzleProgressSnapshot = {
  moves: number;
  wrongDrops: number;
  placed: number;
  total: number;
  combo: number;
  maxCombo: number;
  scorePreview: number;
  completionRatio: number;
};

export type PuzzleDropResolution = {
  piece: PuzzlePieceModel;
  slot: PuzzleSlotModel | null;
  snapped: boolean;
  distance: number | null;
};
