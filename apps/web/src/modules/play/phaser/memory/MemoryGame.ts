import { bootGame, createBridgeFromParams } from '../core/bootGame';
import type { EventovivoPlayableGame } from '../core/runtimeTypes';
import { MemoryScene } from './MemoryScene';
import { normalizeMemorySettings } from './memoryConfig';

export const MemoryGame: EventovivoPlayableGame = {
  key: 'memory',
  boot(params) {
    const scene = new MemoryScene();
    const bridge = createBridgeFromParams(params);

    return bootGame(
      params.container,
      scene,
      {
        ...params.payload,
        settings: normalizeMemorySettings(params.payload.settings),
      },
      bridge,
    );
  },
};
