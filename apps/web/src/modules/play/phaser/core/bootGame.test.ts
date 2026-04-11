import { beforeEach, describe, expect, it, vi } from 'vitest';

import { bootGame } from './bootGame';
import type { GameBootPayload } from './runtimeTypes';
import type { PuzzleGameSettings } from '@/modules/play/types';

const gameConstructorMock = vi.fn();

vi.mock('phaser', () => ({
  default: {
    AUTO: 'AUTO',
    Scale: {
      FIT: 'FIT',
      CENTER_BOTH: 'CENTER_BOTH',
    },
    Game: function PhaserGame(config: Record<string, unknown>) {
      gameConstructorMock(config);
      return {
        config,
      };
    },
  },
}));

function makePayload(): GameBootPayload<PuzzleGameSettings> {
  return {
    eventGameId: 7,
    sessionUuid: 'session-puzzle-boot',
    gameKey: 'puzzle',
    sessionSeed: 'seed-puzzle',
    player: {
      identifier: 'browser-hash',
      name: 'Marina',
    },
    assets: [
      {
        id: 'asset-1',
        url: 'https://cdn.example.com/puzzle.webp',
        mimeType: 'image/webp',
      },
    ],
    settings: {
      gridSize: '3x3',
      snapEnabled: true,
      showReferenceImage: true,
      dragTolerance: 18,
      scoringVersion: 'puzzle_v1',
    },
  };
}

describe('bootGame', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('boots Phaser with FIT scaling, centered canvas and a single active pointer', () => {
    const container = document.createElement('div');
    const scene = {
      scene: {
        key: 'PuzzleScene',
      },
    };
    const payload = makePayload();
    const bridge = { kind: 'bridge' };

    bootGame(container, scene as never, payload, bridge as never);

    expect(gameConstructorMock).toHaveBeenCalledWith(expect.objectContaining({
      type: 'AUTO',
      parent: container,
      backgroundColor: '#020617',
      scale: expect.objectContaining({
        mode: 'FIT',
        autoCenter: 'CENTER_BOTH',
        expandParent: true,
        width: 414,
        height: 896,
      }),
      input: expect.objectContaining({
        activePointers: 1,
      }),
    }));
  });

  it('starts the scene with the payload and bridge during postBoot', () => {
    const container = document.createElement('div');
    const startSpy = vi.fn();
    const scene = {
      scene: {
        key: 'PuzzleScene',
      },
    };
    const payload = makePayload();
    const bridge = { kind: 'bridge' };

    bootGame(container, scene as never, payload, bridge as never);

    const config = gameConstructorMock.mock.calls[0]?.[0] as {
      callbacks: {
        postBoot: (game: { scene: { start: typeof startSpy } }) => void;
      };
    };

    config.callbacks.postBoot({
      scene: {
        start: startSpy,
      },
    });

    expect(startSpy).toHaveBeenCalledWith('PuzzleScene', {
      payload,
      bridge,
    });
  });
});
