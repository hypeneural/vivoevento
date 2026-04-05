import Phaser from 'phaser';

import type { PuzzleGridSpec, PuzzleSceneLayout, PuzzleSlotModel } from '../types/puzzle.types';

type CreatePuzzleSlotsParams = {
  scene: Phaser.Scene;
  grid: PuzzleGridSpec;
  layout: PuzzleSceneLayout;
  pieceKeys: string[];
};

export class PuzzleSlotFactory {
  static create({ scene, grid, layout, pieceKeys }: CreatePuzzleSlotsParams): PuzzleSlotModel[] {
    return pieceKeys.map((textureKey, index) => {
      const col = index % grid.cols;
      const row = Math.floor(index / grid.cols);
      const x = layout.boardX + col * layout.pieceWidth + layout.pieceWidth / 2;
      const y = layout.boardY + row * layout.pieceHeight + layout.pieceHeight / 2;
      const width = layout.pieceWidth - 4;
      const height = layout.pieceHeight - 4;

      const frame = scene.add.rectangle(x, y, width, height, 0xffffff, 0.04)
        .setStrokeStyle(1, 0xffffff, 0.1);
      const highlight = scene.add.rectangle(x, y, width, height, 0x34d399, 0)
        .setStrokeStyle(2, 0x34d399, 0);
      const zone = scene.add.zone(x, y, width, height).setRectangleDropZone(width, height);
      const id = `slot-${row}-${col}`;

      zone.setData('slotId', id);

      return {
        id,
        row,
        col,
        textureKey,
        x,
        y,
        width,
        height,
        zone,
        frame,
        highlight,
        state: 'idle',
        placedPieceId: null,
      };
    });
  }
}
