import type { RestoredGameMove, PuzzleGameSettings, NormalizedGameResult } from '@/modules/play/types';

import { PuzzleCombo } from './PuzzleCombo';
import type { PuzzleProgressSnapshot } from '../types/puzzle.types';

export class PuzzleScore {
  private moves = 0;
  private wrongDrops = 0;
  private correctDrops = 0;
  private readonly combo = new PuzzleCombo();

  hydrateFromMoves(moves: RestoredGameMove[]) {
    const comboSequence: Array<'success' | 'error'> = [];

    for (const move of moves) {
      if (move.type === 'drop') {
        this.moves += 1;

        if (move.payload?.snapped === false) {
          this.wrongDrops += 1;
          comboSequence.push('error');
        }
      }

      if (move.type === 'complete_piece') {
        this.correctDrops += 1;
        comboSequence.push('success');
      }
    }

    this.combo.hydrateFromSequence(comboSequence);
  }

  registerCorrect(now = Date.now()) {
    this.moves += 1;
    this.correctDrops += 1;
    return this.combo.registerSuccess(now);
  }

  registerWrong() {
    this.moves += 1;
    this.wrongDrops += 1;
    this.combo.reset();
    return this.combo.snapshot();
  }

  buildProgress(total: number, placed: number, elapsedMs: number): PuzzleProgressSnapshot {
    const combo = this.combo.snapshot();

    return {
      moves: this.moves,
      wrongDrops: this.wrongDrops,
      placed,
      total,
      combo: combo.current,
      maxCombo: combo.max,
      scorePreview: this.calculateScore(elapsedMs),
      completionRatio: total > 0 ? placed / total : 0,
    };
  }

  buildResult(elapsedMs: number, settings: PuzzleGameSettings): NormalizedGameResult {
    const combo = this.combo.snapshot();

    return {
      completed: true,
      score: this.calculateScore(elapsedMs),
      timeMs: elapsedMs,
      moves: this.moves,
      mistakes: this.wrongDrops,
      accuracy: this.moves > 0 ? (this.moves - this.wrongDrops) / this.moves : 1,
      metadata: {
        gridSize: settings.gridSize,
        scoringVersion: settings.scoringVersion ?? 'puzzle_v1',
        combo: combo.current,
        maxCombo: combo.max,
        correctDrops: this.correctDrops,
      },
    };
  }

  private calculateScore(elapsedMs: number) {
    const elapsedSeconds = Math.ceil(elapsedMs / 1000);
    return Math.max(0, 1200 - (elapsedSeconds * 5) - (this.moves * 2) - (this.wrongDrops * 8));
  }
}
