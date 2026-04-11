import { act, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { usePhaserGame } from './usePhaserGame';
import type { GameBootPayload } from '@/modules/play/phaser/core/runtimeTypes';
import type { PuzzleGameSettings } from '@/modules/play/types';

const registerDefaultGamesMock = vi.fn();
const loadMock = vi.fn();
const destroyGameInstanceMock = vi.fn();

vi.mock('@/modules/play/phaser/registerDefaultGames', () => ({
  registerDefaultGames: () => registerDefaultGamesMock(),
}));

vi.mock('@/modules/play/phaser/core/GameRegistry', () => ({
  GameRegistry: {
    load: (...args: unknown[]) => loadMock(...args),
  },
}));

vi.mock('@/modules/play/phaser/core/cleanup', () => ({
  destroyGameInstance: (...args: unknown[]) => destroyGameInstanceMock(...args),
}));

function makePayload(overrides: Partial<GameBootPayload<PuzzleGameSettings>> = {}): GameBootPayload<PuzzleGameSettings> {
  return {
    eventGameId: 5,
    sessionUuid: 'session-puzzle-123',
    gameKey: 'puzzle',
    sessionSeed: 'seed-123',
    player: {
      identifier: 'browser-hash',
      name: 'Anderson',
    },
    assets: [
      {
        id: 'asset-1',
        url: 'https://cdn.example.com/puzzle-cover.webp',
        mimeType: 'image/webp',
        variantKey: 'wall',
        deliveryProfile: 'rich',
        width: 1600,
        height: 1600,
      },
    ],
    settings: {
      gridSize: '3x3',
      snapEnabled: true,
      showReferenceImage: true,
      dragTolerance: 18,
      scoringVersion: 'puzzle_v1',
    },
    ...overrides,
  };
}

type HarnessProps = {
  payload: GameBootPayload<PuzzleGameSettings> | null;
  enabled?: boolean;
  onReady?: () => void;
};

function PhaserHarness({ payload, enabled = true, onReady }: HarnessProps) {
  const { containerRef, runtimeStatus, runtimeError } = usePhaserGame({
    payload,
    enabled,
    onReady,
    onFinish: vi.fn(),
  });

  return (
    <div>
      <div data-testid="runtime-status">{runtimeStatus}</div>
      <div data-testid="runtime-error">{runtimeError?.message ?? ''}</div>
      <div data-testid="runtime-container" ref={containerRef} />
    </div>
  );
}

describe('usePhaserGame', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  it('keeps the runtime in loading until the game emits onReady', async () => {
    const fakeGame = { key: 'phaser-game-instance' };
    const bootMock = vi.fn().mockReturnValue(fakeGame);
    const onReady = vi.fn();

    loadMock.mockResolvedValue({
      key: 'puzzle',
      boot: bootMock,
    });

    render(<PhaserHarness payload={makePayload()} onReady={onReady} />);

    await waitFor(() => {
      expect(bootMock).toHaveBeenCalledTimes(1);
    });

    expect(registerDefaultGamesMock).toHaveBeenCalledTimes(1);
    expect(loadMock).toHaveBeenCalledWith('puzzle');
    expect(screen.getByTestId('runtime-status')).toHaveTextContent('loading');
    expect(onReady).not.toHaveBeenCalled();

    const params = bootMock.mock.calls[0]?.[0] as {
      onReady?: () => void;
    };

    await act(async () => {
      params.onReady?.();
    });

    await waitFor(() => {
      expect(screen.getByTestId('runtime-status')).toHaveTextContent('ready');
    });
    expect(onReady).toHaveBeenCalledTimes(1);
  });

  it('switches to error when the runtime emits onError before becoming ready', async () => {
    const fakeGame = { key: 'phaser-game-instance' };
    const bootMock = vi.fn().mockReturnValue(fakeGame);

    loadMock.mockResolvedValue({
      key: 'puzzle',
      boot: bootMock,
    });

    render(<PhaserHarness payload={makePayload()} />);

    await waitFor(() => {
      expect(bootMock).toHaveBeenCalledTimes(1);
    });

    const params = bootMock.mock.calls[0]?.[0] as {
      onError?: (error: Error | string) => void;
    };

    await act(async () => {
      params.onError?.(new Error('Nenhuma foto valida disponivel para montar o puzzle.'));
    });

    await waitFor(() => {
      expect(screen.getByTestId('runtime-status')).toHaveTextContent('error');
    });
    expect(screen.getByTestId('runtime-error')).toHaveTextContent('Nenhuma foto valida disponivel para montar o puzzle.');
  });

  it('destroys the Phaser instance on unmount', async () => {
    const fakeGame = { key: 'phaser-game-instance' };
    const bootMock = vi.fn().mockReturnValue(fakeGame);

    loadMock.mockResolvedValue({
      key: 'puzzle',
      boot: bootMock,
    });

    const view = render(<PhaserHarness payload={makePayload()} />);

    await waitFor(() => {
      expect(bootMock).toHaveBeenCalledTimes(1);
    });

    const params = bootMock.mock.calls[0]?.[0] as {
      onReady?: () => void;
    };
    await act(async () => {
      params.onReady?.();
    });

    await waitFor(() => {
      expect(screen.getByTestId('runtime-status')).toHaveTextContent('ready');
    });

    view.unmount();

    expect(destroyGameInstanceMock).toHaveBeenCalledWith(fakeGame);
  });
});
