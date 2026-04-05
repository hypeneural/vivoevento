import Phaser from 'phaser';

import { PUZZLE_PARTICLE_TEXTURE_KEY } from '../config/puzzleAssets';
import type { PuzzlePieceModel, PuzzleSlotModel } from '../types/puzzle.types';

export class PuzzleFeedbackSystem {
  private readonly particles: Phaser.GameObjects.Particles.ParticleEmitter;

  constructor(private readonly scene: Phaser.Scene) {
    this.ensureParticleTexture();
    this.particles = this.scene.add.particles(0, 0, PUZZLE_PARTICLE_TEXTURE_KEY, {
      lifespan: 420,
      speed: { min: 45, max: 120 },
      scale: { start: 0.22, end: 0 },
      alpha: { start: 0.95, end: 0 },
      blendMode: 'ADD',
      emitting: false,
    });
  }

  onDragStart(piece: PuzzlePieceModel) {
    this.scene.children.bringToTop(piece.sprite);
    piece.sprite.setDepth(20);
    this.scene.tweens.killTweensOf(piece.sprite);
    this.scene.tweens.add({
      targets: piece.sprite,
      scaleX: piece.sprite.scaleX * 1.06,
      scaleY: piece.sprite.scaleY * 1.06,
      duration: 110,
      ease: 'Quad.Out',
    });
  }

  onDragEnd(piece: PuzzlePieceModel) {
    if (piece.isLocked) {
      piece.sprite.setDepth(6);
      return;
    }

    this.scene.tweens.killTweensOf(piece.sprite);
    this.scene.tweens.add({
      targets: piece.sprite,
      scaleX: piece.homeScaleX,
      scaleY: piece.homeScaleY,
      duration: 100,
      ease: 'Quad.Out',
    });
    piece.sprite.setDepth(10);
  }

  highlightSlot(slot: PuzzleSlotModel, variant: 'available' | 'invalid') {
    slot.state = variant;
    const color = variant === 'available' ? 0x34d399 : 0xfb7185;

    slot.frame.setStrokeStyle(2, color, 0.45);
    slot.highlight.setFillStyle(color, variant === 'available' ? 0.14 : 0.12);
    slot.highlight.setStrokeStyle(2, color, 0.7);
  }

  clearSlotHighlight(slot: PuzzleSlotModel) {
    if (slot.state === 'locked') {
      return;
    }

    slot.state = 'idle';
    slot.frame.setStrokeStyle(1, 0xffffff, 0.1);
    slot.highlight.setFillStyle(0x34d399, 0);
    slot.highlight.setStrokeStyle(2, 0x34d399, 0);
  }

  lockSlot(slot: PuzzleSlotModel) {
    slot.state = 'locked';
    slot.frame.setStrokeStyle(2, 0x34d399, 0.35);
    slot.highlight.setFillStyle(0x34d399, 0.08);
    slot.highlight.setStrokeStyle(2, 0x34d399, 0.42);
  }

  animateCorrectPlacement(
    piece: PuzzlePieceModel,
    slot: PuzzleSlotModel,
    onComplete?: () => void,
  ) {
    this.scene.tweens.killTweensOf(piece.sprite);
    this.scene.children.bringToTop(piece.sprite);
    piece.sprite.setDepth(24);

    this.scene.tweens.add({
      targets: piece.sprite,
      x: slot.x,
      y: slot.y,
      scaleX: piece.boardScaleX,
      scaleY: piece.boardScaleY,
      duration: 180,
      ease: 'Back.Out',
      onComplete: () => {
        piece.sprite.setDepth(6);
        this.lockSlot(slot);
        this.emitParticles(slot.x, slot.y, 14);
        this.scene.tweens.add({
          targets: slot.highlight,
          alpha: 0.16,
          duration: 140,
          yoyo: true,
        });
        onComplete?.();
      },
    });
  }

  animateWrongPlacement(piece: PuzzlePieceModel, onComplete?: () => void) {
    this.scene.tweens.killTweensOf(piece.sprite);

    this.scene.tweens.add({
      targets: piece.sprite,
      x: piece.sprite.x + Phaser.Math.Between(-12, 12),
      duration: 50,
      yoyo: true,
      repeat: 2,
      onComplete: () => {
        this.scene.tweens.add({
          targets: piece.sprite,
          x: piece.homeX,
          y: piece.homeY,
          scaleX: piece.homeScaleX,
          scaleY: piece.homeScaleY,
          duration: 260,
          ease: 'Cubic.Out',
          onComplete,
        });
      },
    });
  }

  burstAt(x: number, y: number, quantity = 20) {
    this.emitParticles(x, y, quantity);
  }

  private ensureParticleTexture() {
    if (this.scene.textures.exists(PUZZLE_PARTICLE_TEXTURE_KEY)) {
      return;
    }

    const graphics = this.scene.add.graphics();
    graphics.fillStyle(0xfff1bf, 1);
    graphics.fillCircle(8, 8, 8);
    graphics.generateTexture(PUZZLE_PARTICLE_TEXTURE_KEY, 16, 16);
    graphics.destroy();
  }

  private emitParticles(x: number, y: number, quantity: number) {
    this.particles.explode(quantity, x, y);
  }
}
