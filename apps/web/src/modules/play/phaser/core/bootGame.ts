import Phaser from 'phaser';

import { BaseGameBridge } from './BaseGameBridge';
import type { BasePlayScene } from './BasePlayScene';
import type { CreateGameParams, GameBootPayload } from './runtimeTypes';

export function bootGame<TSettings>(
  container: HTMLDivElement,
  scene: BasePlayScene<TSettings>,
  payload: GameBootPayload<TSettings>,
  bridge: BaseGameBridge,
  width = 414,
  height = 896,
) {
  return new Phaser.Game({
    type: Phaser.AUTO,
    width,
    height,
    parent: container,
    backgroundColor: '#020617',
    scene: [scene],
    scale: {
      mode: Phaser.Scale.FIT,
      autoCenter: Phaser.Scale.CENTER_BOTH,
      expandParent: true,
      width,
      height,
    },
    input: {
      activePointers: 1,
    },
    render: {
      antialias: true,
      roundPixels: false,
    },
    callbacks: {
      postBoot: (game) => {
        game.scene.start(scene.scene.key, { payload, bridge });
      },
    },
  });
}

export function createBridgeFromParams(params: CreateGameParams) {
  return new BaseGameBridge({
    onReady: params.onReady,
    onError: params.onError,
    onProgress: params.onProgress,
    onMove: params.onMove,
    onFinish: params.onFinish,
  });
}
