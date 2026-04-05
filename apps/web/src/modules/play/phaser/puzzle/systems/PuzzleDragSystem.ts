import Phaser from 'phaser';

import type { PuzzleBoardState, PuzzlePieceModel, PuzzleProgressSnapshot, PuzzleSlotModel } from '../types/puzzle.types';
import { PuzzleHudBridge } from '../ui/PuzzleHudBridge';
import { PuzzleAudioSystem } from './PuzzleAudioSystem';
import { PuzzleFeedbackSystem } from './PuzzleFeedbackSystem';
import { PuzzlePlacementSystem } from './PuzzlePlacementSystem';
import { PuzzleVictorySystem } from './PuzzleVictorySystem';

type PuzzleDragSystemParams = {
  scene: Phaser.Scene;
  board: PuzzleBoardState;
  placement: PuzzlePlacementSystem;
  feedback: PuzzleFeedbackSystem;
  audio: PuzzleAudioSystem;
  victory: PuzzleVictorySystem;
  hud: PuzzleHudBridge;
  getElapsedMs: () => number;
  onSolved: () => void;
  emitProgress: (snapshot: PuzzleProgressSnapshot, phase?: 'ready' | 'progress' | 'victory') => void;
};

export class PuzzleDragSystem {
  private readonly slotMap = new Map<Phaser.GameObjects.Zone, PuzzleSlotModel>();

  constructor(private readonly params: PuzzleDragSystemParams) {
    this.params.board.slots.forEach((slot) => {
      this.slotMap.set(slot.zone, slot);
    });
  }

  bind() {
    this.params.scene.input.on('dragstart', this.handleDragStart, this);
    this.params.scene.input.on('drag', this.handleDrag, this);
    this.params.scene.input.on('dragenter', this.handleDragEnter, this);
    this.params.scene.input.on('dragleave', this.handleDragLeave, this);
    this.params.scene.input.on('drop', this.handleDrop, this);
    this.params.scene.input.on('dragend', this.handleDragEnd, this);
  }

  destroy() {
    this.params.scene.input.off('dragstart', this.handleDragStart, this);
    this.params.scene.input.off('drag', this.handleDrag, this);
    this.params.scene.input.off('dragenter', this.handleDragEnter, this);
    this.params.scene.input.off('dragleave', this.handleDragLeave, this);
    this.params.scene.input.off('drop', this.handleDrop, this);
    this.params.scene.input.off('dragend', this.handleDragEnd, this);
  }

  private handleDragStart(
    _pointer: Phaser.Input.Pointer,
    gameObject: Phaser.GameObjects.Image,
  ) {
    const piece = this.getPieceBySprite(gameObject);
    if (!piece || piece.isLocked) {
      return;
    }

    piece.dropSlotId = null;
    piece.activeSlotId = null;
    this.params.audio.playPickup();
    this.params.feedback.onDragStart(piece);
    this.params.hud.move('drag_start', {
      pieceKey: piece.textureKey,
    });
  }

  private handleDrag(
    _pointer: Phaser.Input.Pointer,
    gameObject: Phaser.GameObjects.Image,
    dragX: number,
    dragY: number,
  ) {
    const piece = this.getPieceBySprite(gameObject);
    if (!piece || piece.isLocked) {
      return;
    }

    piece.sprite.x = dragX;
    piece.sprite.y = dragY;
  }

  private handleDragEnter(
    _pointer: Phaser.Input.Pointer,
    gameObject: Phaser.GameObjects.Image,
    dropZone: Phaser.GameObjects.Zone,
  ) {
    const piece = this.getPieceBySprite(gameObject);
    const slot = this.slotMap.get(dropZone);

    if (!piece || !slot || piece.isLocked || slot.placedPieceId) {
      return;
    }

    piece.activeSlotId = slot.id;
    const variant = piece.slotId === slot.id ? 'available' : 'invalid';
    this.params.feedback.highlightSlot(slot, variant);

    if (variant === 'available') {
      this.params.audio.playHover();
    }
  }

  private handleDragLeave(
    _pointer: Phaser.Input.Pointer,
    gameObject: Phaser.GameObjects.Image,
    dropZone: Phaser.GameObjects.Zone,
  ) {
    const piece = this.getPieceBySprite(gameObject);
    const slot = this.slotMap.get(dropZone);

    if (!piece || !slot || piece.isLocked) {
      return;
    }

    if (piece.activeSlotId === slot.id) {
      piece.activeSlotId = null;
    }

    this.params.feedback.clearSlotHighlight(slot);
  }

  private handleDrop(
    _pointer: Phaser.Input.Pointer,
    gameObject: Phaser.GameObjects.Image,
    dropZone: Phaser.GameObjects.Zone,
  ) {
    const piece = this.getPieceBySprite(gameObject);
    const slot = this.slotMap.get(dropZone);

    if (!piece || !slot || piece.isLocked) {
      return;
    }

    piece.dropSlotId = slot.id;
  }

  private handleDragEnd(
    _pointer: Phaser.Input.Pointer,
    gameObject: Phaser.GameObjects.Image,
    dropped: boolean,
  ) {
    const piece = this.getPieceBySprite(gameObject);
    if (!piece || piece.isLocked) {
      return;
    }

    const slot = piece.dropSlotId ? this.params.board.slotsById.get(piece.dropSlotId) ?? null : null;
    const outcome = this.params.placement.resolveDrop(piece, dropped ? slot : null, this.params.getElapsedMs());

    if (piece.activeSlotId) {
      const activeSlot = this.params.board.slotsById.get(piece.activeSlotId);
      if (activeSlot) {
        this.params.feedback.clearSlotHighlight(activeSlot);
      }
    }

    if (slot) {
      this.params.feedback.clearSlotHighlight(slot);
    }

    this.params.feedback.onDragEnd(piece);
    this.params.emitProgress(outcome.progress, 'progress');
    this.params.hud.move('drop', {
      pieceKey: piece.textureKey,
      slotId: slot?.id ?? null,
      distance: outcome.resolution.distance,
      snapped: outcome.resolution.snapped,
      moveCount: outcome.progress.moves,
      wrongDrops: outcome.progress.wrongDrops,
      combo: outcome.progress.combo,
    });

    if (outcome.resolution.snapped) {
      this.params.hud.move('complete_piece', {
        pieceKey: piece.textureKey,
        slotId: slot?.id ?? null,
        placed: this.params.board.placedCount,
        total: this.params.board.totalPieces,
      });

      if (outcome.progress.combo > 1) {
        this.params.hud.move('combo', {
          combo: outcome.progress.combo,
          maxCombo: outcome.progress.maxCombo,
        });
      }
    }

    piece.dropSlotId = null;
    piece.activeSlotId = null;

    if (outcome.solved) {
      this.params.emitProgress(outcome.progress, 'victory');
      this.params.hud.move('victory', {
        totalMoves: outcome.progress.moves,
        wrongDrops: outcome.progress.wrongDrops,
      });
      this.params.victory.play(() => {
        this.params.onSolved();
      });
    }
  }

  private getPieceBySprite(gameObject: Phaser.GameObjects.Image) {
    const pieceId = String(gameObject.getData('pieceId') ?? '');
    return this.params.board.piecesById.get(pieceId) ?? null;
  }
}
