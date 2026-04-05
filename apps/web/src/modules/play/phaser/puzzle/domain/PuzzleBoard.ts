import type Phaser from 'phaser';

import type { PuzzleGameSettings, RestoredGameMove } from '@/modules/play/types';

import { resolveSceneViewport } from '../../core/viewport';
import { resolvePuzzleGrid } from '../config/puzzleConfig';
import type {
  PuzzleBoardState,
  PuzzleGridSpec,
  PuzzlePieceModel,
  PuzzleSceneLayout,
  PuzzleSlotModel,
} from '../types/puzzle.types';

export function buildPuzzleSceneLayout(
  scene: Phaser.Scene,
  settings: Pick<PuzzleGameSettings, 'gridSize' | 'showReferenceImage'>,
): PuzzleSceneLayout {
  const grid = resolvePuzzleGrid(settings.gridSize);
  const viewport = resolveSceneViewport(scene, {
    hudHeight: settings.showReferenceImage ? 148 : 94,
    sideInset: 20,
    topInset: 24,
    bottomInset: 28,
  });
  const trayHeight = grid.rows === 2 ? 190 : 244;
  const boardSize = Math.min(viewport.boardRect.w, viewport.boardRect.h - trayHeight - 18);
  const boardX = viewport.boardRect.x + (viewport.boardRect.w - boardSize) / 2;
  const boardY = viewport.boardRect.y;
  const trayGap = 10;
  const trayColumns = grid.cols === 2 ? 2 : 3;
  const trayRows = Math.ceil((grid.rows * grid.cols) / trayColumns);
  const trayRect = {
    x: viewport.boardRect.x,
    y: boardY + boardSize + 18,
    w: viewport.boardRect.w,
    h: trayHeight,
  };
  const pieceWidth = Math.floor(boardSize / grid.cols);
  const pieceHeight = Math.floor(boardSize / grid.rows);
  const trayPieceWidth = Math.floor(Math.min(
    pieceWidth,
    (trayRect.w - trayGap * (trayColumns - 1) - 28) / trayColumns,
  ));
  const trayPieceHeight = Math.floor(Math.min(
    pieceHeight,
    (trayRect.h - trayGap * (trayRows - 1) - 28) / Math.max(1, trayRows),
  ));

  return {
    viewport,
    boardRect: {
      x: boardX,
      y: boardY,
      w: boardSize,
      h: boardSize,
    },
    trayRect,
    boardX,
    boardY,
    boardSize,
    pieceWidth,
    pieceHeight,
    trayColumns,
    trayRows,
    trayGap,
    trayPieceWidth,
    trayPieceHeight,
  };
}

export function createPuzzleBoardState(
  grid: PuzzleGridSpec,
  layout: PuzzleSceneLayout,
  slots: PuzzleSlotModel[],
  pieces: PuzzlePieceModel[],
): PuzzleBoardState {
  const slotsById = new Map(slots.map((slot) => [slot.id, slot]));
  const piecesById = new Map(pieces.map((piece) => [piece.id, piece]));
  const placedCount = pieces.filter((piece) => piece.isLocked).length;

  return {
    grid,
    layout,
    slots,
    pieces,
    slotsById,
    piecesById,
    placedCount,
    totalPieces: pieces.length,
  };
}

export function getRestoredPieceKeys(moves: RestoredGameMove[]) {
  return new Set(
    moves
      .filter((move) => move.type === 'complete_piece')
      .map((move) => String(move.payload?.pieceKey ?? ''))
      .filter(Boolean),
  );
}

export function isPieceCorrectForSlot(piece: PuzzlePieceModel, slot: PuzzleSlotModel) {
  return piece.slotId === slot.id;
}

export function markPiecePlaced(board: PuzzleBoardState, piece: PuzzlePieceModel, slot: PuzzleSlotModel) {
  if (piece.isLocked) {
    return;
  }

  piece.isLocked = true;
  piece.currentSlotId = slot.id;
  slot.placedPieceId = piece.id;
  slot.state = 'locked';
  board.placedCount += 1;
}

export function isPuzzleSolved(board: PuzzleBoardState) {
  return board.placedCount >= board.totalPieces;
}
