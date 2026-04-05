import type { NormalizedGameResult } from '@/modules/play/types';
import type { GameRuntimeMove } from './runtimeTypes';

export type GameBridgeCallbacks = {
  onReady?: () => void;
  onProgress?: (data: Record<string, unknown>) => void;
  onMove?: (move: GameRuntimeMove) => void;
  onFinish?: (result: NormalizedGameResult) => void;
};

export class BaseGameBridge {
  constructor(private readonly callbacks: GameBridgeCallbacks = {}) {}

  ready() {
    this.callbacks.onReady?.();
  }

  progress(data: Record<string, unknown>) {
    this.callbacks.onProgress?.(data);
  }

  move(move: GameRuntimeMove) {
    this.callbacks.onMove?.(move);
  }

  finish(result: NormalizedGameResult) {
    this.callbacks.onFinish?.(result);
  }
}
