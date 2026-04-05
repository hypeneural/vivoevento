import Phaser from 'phaser';

import { seededShuffle } from '../../core/shuffle';
import type {
  PuzzleGridSpec,
  PuzzlePieceModel,
  PuzzleSceneLayout,
  PuzzleSlotModel,
} from '../types/puzzle.types';

type CreatePuzzlePiecesParams = {
  scene: Phaser.Scene;
  grid: PuzzleGridSpec;
  layout: PuzzleSceneLayout;
  slots: PuzzleSlotModel[];
  sessionSeed: string;
  restoredPieceKeys: Set<string>;
};

export class PuzzlePieceFactory {
  static createTextures(
    scene: Phaser.Scene,
    textureKey: string,
    rows: number,
    cols: number,
  ) {
    const source = scene.textures.get(textureKey).getSourceImage() as HTMLImageElement | HTMLCanvasElement;
    const pieceWidth = Math.floor(source.width / cols);
    const pieceHeight = Math.floor(source.height / rows);
    const keys: string[] = [];

    for (let row = 0; row < rows; row += 1) {
      for (let col = 0; col < cols; col += 1) {
        const canvas = document.createElement('canvas');
        canvas.width = pieceWidth;
        canvas.height = pieceHeight;

        const context = canvas.getContext('2d');
        if (!context) continue;

        context.drawImage(
          source,
          col * pieceWidth,
          row * pieceHeight,
          pieceWidth,
          pieceHeight,
          0,
          0,
          pieceWidth,
          pieceHeight,
        );

        const key = `${textureKey}-piece-${row}-${col}`;
        if (scene.textures.exists(key)) {
          scene.textures.remove(key);
        }

        scene.textures.addCanvas(key, canvas);
        keys.push(key);
      }
    }

    return keys;
  }

  static create({
    scene,
    grid,
    layout,
    slots,
    sessionSeed,
    restoredPieceKeys,
  }: CreatePuzzlePiecesParams): PuzzlePieceModel[] {
    const shuffledSlots = seededShuffle(slots, `${sessionSeed}:puzzle-piece-order`);

    return shuffledSlots.map((slot, index) => {
      const trayCol = index % layout.trayColumns;
      const trayRow = Math.floor(index / layout.trayColumns);
      const homeX = layout.trayRect.x + 18 + trayCol * (layout.trayPieceWidth + layout.trayGap) + layout.trayPieceWidth / 2;
      const homeY = layout.trayRect.y + 18 + trayRow * (layout.trayPieceHeight + layout.trayGap) + layout.trayPieceHeight / 2;
      const restored = restoredPieceKeys.has(slot.textureKey);
      const sprite = scene.add.image(restored ? slot.x : homeX, restored ? slot.y : homeY, slot.textureKey)
        .setDisplaySize(layout.pieceWidth, layout.pieceHeight)
        .setScale(
          restored ? 1 : layout.trayPieceWidth / layout.pieceWidth,
          restored ? 1 : layout.trayPieceHeight / layout.pieceHeight,
        )
        .setInteractive({ draggable: true, useHandCursor: true });

      const piece: PuzzlePieceModel = {
        id: `piece-${slot.id}`,
        slotId: slot.id,
        textureKey: slot.textureKey,
        sprite,
        homeX,
        homeY,
        homeScaleX: layout.trayPieceWidth / layout.pieceWidth,
        homeScaleY: layout.trayPieceHeight / layout.pieceHeight,
        targetX: slot.x,
        targetY: slot.y,
        boardScaleX: 1,
        boardScaleY: 1,
        isLocked: restored,
        currentSlotId: restored ? slot.id : null,
        activeSlotId: null,
        dropSlotId: null,
      };

      sprite.setData('pieceId', piece.id);
      sprite.setData('textureKey', piece.textureKey);
      scene.input.setDraggable(sprite);

      if (restored) {
        slot.placedPieceId = piece.id;
        slot.state = 'locked';
      }

      return piece;
    });
  }
}
