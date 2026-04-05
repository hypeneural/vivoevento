import Phaser from 'phaser';

import type { BaseGameBridge } from './BaseGameBridge';
import type { GameBootPayload } from './runtimeTypes';

export abstract class BasePlayScene<TSettings = Record<string, unknown>> extends Phaser.Scene {
  protected payload!: GameBootPayload<TSettings>;
  protected bridge!: BaseGameBridge;
  private startedAt = 0;

  constructor(key: string) {
    super(key);
  }

  init(data: { payload: GameBootPayload<TSettings>; bridge: BaseGameBridge }) {
    this.payload = data.payload;
    this.bridge = data.bridge;
    this.startedAt = Date.now();
  }

  protected elapsedMs() {
    return Date.now() - this.startedAt;
  }
}
