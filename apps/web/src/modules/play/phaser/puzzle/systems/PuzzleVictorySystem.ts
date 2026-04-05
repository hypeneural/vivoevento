import Phaser from 'phaser';

import { PUZZLE_SOURCE_TEXTURE_KEY } from '../config/puzzleAssets';
import type { PuzzleBoardState } from '../types/puzzle.types';
import { PuzzleAudioSystem } from './PuzzleAudioSystem';
import { PuzzleFeedbackSystem } from './PuzzleFeedbackSystem';

export class PuzzleVictorySystem {
  constructor(
    private readonly scene: Phaser.Scene,
    private readonly board: PuzzleBoardState,
    private readonly feedback: PuzzleFeedbackSystem,
    private readonly audio: PuzzleAudioSystem,
  ) {}

  play(onComplete: () => void) {
    this.scene.input.enabled = false;
    this.audio.playVictory();

    const overlay = this.scene.add.image(
      this.board.layout.boardX + this.board.layout.boardSize / 2,
      this.board.layout.boardY + this.board.layout.boardSize / 2,
      PUZZLE_SOURCE_TEXTURE_KEY,
    )
      .setDisplaySize(this.board.layout.boardSize, this.board.layout.boardSize)
      .setAlpha(0)
      .setScale(1.03)
      .setDepth(30);

    this.board.slots.forEach((slot) => {
      this.scene.tweens.add({
        targets: [slot.frame, slot.highlight],
        alpha: 0,
        duration: 240,
        ease: 'Quad.Out',
      });
    });

    this.feedback.burstAt(
      this.board.layout.boardX + this.board.layout.boardSize / 2,
      this.board.layout.boardY + this.board.layout.boardSize / 2,
      32,
    );

    this.scene.cameras.main.shake(180, 0.0025);
    this.scene.cameras.main.flash(220, 255, 255, 255, false);

    this.scene.tweens.add({
      targets: overlay,
      alpha: 1,
      scale: 1,
      duration: 260,
      ease: 'Sine.Out',
    });

    this.scene.time.delayedCall(700, () => {
      onComplete();
    });
  }
}
