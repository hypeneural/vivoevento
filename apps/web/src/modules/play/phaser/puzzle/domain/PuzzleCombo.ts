import type { PuzzleComboSnapshot } from '../types/puzzle.types';

export class PuzzleCombo {
  private current = 0;
  private max = 0;
  private lastSuccessAt = 0;

  constructor(private readonly hotWindowMs = 2200) {}

  registerSuccess(now: number): PuzzleComboSnapshot {
    if (this.lastSuccessAt > 0 && now - this.lastSuccessAt <= this.hotWindowMs) {
      this.current += 1;
    } else {
      this.current = 1;
    }

    this.lastSuccessAt = now;
    this.max = Math.max(this.max, this.current);

    return this.snapshot(now);
  }

  reset() {
    this.current = 0;
    this.lastSuccessAt = 0;
  }

  hydrateFromSequence(sequence: Array<'success' | 'error'>) {
    let streak = 0;

    for (const entry of sequence) {
      if (entry === 'success') {
        streak += 1;
        this.max = Math.max(this.max, streak);
      } else {
        streak = 0;
      }
    }

    this.current = streak;
  }

  snapshot(now = Date.now()): PuzzleComboSnapshot {
    return {
      current: this.current,
      max: this.max,
      isHot: this.current > 1 && this.lastSuccessAt > 0 && now - this.lastSuccessAt <= this.hotWindowMs,
    };
  }
}
