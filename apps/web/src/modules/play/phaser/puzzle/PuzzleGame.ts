import { bootGame, createBridgeFromParams } from '../core/bootGame';
import type { EventovivoPlayableGame } from '../core/runtimeTypes';
import { normalizePuzzleSettings } from './config/puzzleConfig';
import { PuzzleScene } from './PuzzleScene';

export const PuzzleGame: EventovivoPlayableGame = {
  key: 'puzzle',
  boot(params) {
    const scene = new PuzzleScene();
    const bridge = createBridgeFromParams(params);

    return bootGame(
      params.container,
      scene,
      {
        ...params.payload,
        settings: normalizePuzzleSettings(params.payload.settings),
      },
      bridge,
    );
  },
};
