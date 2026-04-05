import type { NormalizedGameResult } from '@/modules/play/types';

import type { BaseGameBridge } from '../../core/BaseGameBridge';
import type { PuzzleProgressSnapshot } from '../types/puzzle.types';

export class PuzzleHudBridge {
  constructor(private readonly bridge: BaseGameBridge) {}

  ready(snapshot?: PuzzleProgressSnapshot) {
    if (snapshot) {
      this.progress(snapshot, 'ready');
    }

    this.bridge.ready();
  }

  progress(snapshot: PuzzleProgressSnapshot, phase: 'ready' | 'progress' | 'victory' = 'progress') {
    this.bridge.progress({
      phase,
      ...snapshot,
    });
  }

  move(moveType: string, payload?: Record<string, unknown>) {
    this.bridge.move({
      moveType,
      payload,
    });
  }

  finish(result: NormalizedGameResult) {
    this.bridge.finish(result);
  }
}
