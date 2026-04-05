import Phaser from 'phaser';

import { PUZZLE_AUDIO_ASSETS, PUZZLE_AUDIO_KEYS } from '../config/puzzleAssets';

export class PuzzleAudioSystem {
  static preload(scene: Phaser.Scene) {
    Object.entries(PUZZLE_AUDIO_ASSETS).forEach(([key, assetPath]) => {
      scene.load.audio(key, assetPath);
    });
  }

  constructor(private readonly scene: Phaser.Scene) {}

  playPickup() {
    this.play(PUZZLE_AUDIO_KEYS.pickup, { volume: 0.18, rate: 1.08 });
  }

  playHover() {
    this.play(PUZZLE_AUDIO_KEYS.hover, { volume: 0.1, rate: 1.02 });
  }

  playSnap() {
    this.play(PUZZLE_AUDIO_KEYS.snap, { volume: 0.22, rate: 1 });
  }

  playError() {
    this.play(PUZZLE_AUDIO_KEYS.error, { volume: 0.18, rate: 0.96 });
  }

  playVictory() {
    this.play(PUZZLE_AUDIO_KEYS.victory, { volume: 0.24, rate: 1 });
  }

  private play(key: string, config?: Phaser.Types.Sound.SoundConfig) {
    if (!this.scene.cache.audio.exists(key)) {
      return;
    }

    this.scene.sound.play(key, config);
  }
}
