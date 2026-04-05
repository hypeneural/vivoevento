import { useEffect, useRef, useState } from 'react';
import type Phaser from 'phaser';

import { destroyGameInstance } from '@/modules/play/phaser/core/cleanup';
import { GameRegistry } from '@/modules/play/phaser/core/GameRegistry';
import { registerDefaultGames } from '@/modules/play/phaser/registerDefaultGames';
import type { GameBootPayload } from '@/modules/play/phaser/core/runtimeTypes';
import type { NormalizedGameResult } from '@/modules/play/types';
import type { GameRuntimeMove } from '@/modules/play/phaser/core/runtimeTypes';

type UsePhaserGameParams = {
  payload: GameBootPayload | null;
  enabled?: boolean;
  onFinish: (result: NormalizedGameResult) => void;
  onReady?: () => void;
  onProgress?: (data: Record<string, unknown>) => void;
  onMove?: (move: GameRuntimeMove) => void;
};

export type PhaserRuntimeStatus = 'idle' | 'loading' | 'ready' | 'error';

export function usePhaserGame({
  payload,
  enabled = true,
  onFinish,
  onReady,
  onProgress,
  onMove,
}: UsePhaserGameParams) {
  const containerRef = useRef<HTMLDivElement | null>(null);
  const gameRef = useRef<Phaser.Game | null>(null);
  const onFinishRef = useRef(onFinish);
  const onReadyRef = useRef(onReady);
  const onProgressRef = useRef(onProgress);
  const onMoveRef = useRef(onMove);
  const [runtimeStatus, setRuntimeStatus] = useState<PhaserRuntimeStatus>('idle');
  const [runtimeError, setRuntimeError] = useState<Error | null>(null);

  useEffect(() => {
    onFinishRef.current = onFinish;
  }, [onFinish]);

  useEffect(() => {
    onReadyRef.current = onReady;
  }, [onReady]);

  useEffect(() => {
    onProgressRef.current = onProgress;
  }, [onProgress]);

  useEffect(() => {
    onMoveRef.current = onMove;
  }, [onMove]);

  useEffect(() => {
    if (!enabled || !payload || !containerRef.current) {
      setRuntimeStatus('idle');
      setRuntimeError(null);
      return;
    }

    registerDefaultGames();
    setRuntimeStatus('loading');
    setRuntimeError(null);

    let disposed = false;
    let cleanupRuntime: (() => void) | null = null;

    void GameRegistry.load(payload.gameKey)
      .then((gameDefinition) => {
        if (disposed || !containerRef.current) {
          return;
        }

        const game = gameDefinition.boot({
          container: containerRef.current,
          payload,
          onReady: () => onReadyRef.current?.(),
          onProgress: (data) => onProgressRef.current?.(data),
          onMove: (move) => onMoveRef.current?.(move),
          onFinish: (result) => onFinishRef.current(result),
        });

        gameRef.current = game;
        setRuntimeStatus('ready');
        setRuntimeError(null);
        cleanupRuntime = () => {
          if (gameDefinition.destroy) {
            gameDefinition.destroy(game);
          } else {
            destroyGameInstance(game);
          }
        };
      })
      .catch((error) => {
        if (disposed) {
          return;
        }

        setRuntimeStatus('error');
        setRuntimeError(error instanceof Error ? error : new Error('Falha ao carregar o runtime do jogo.'));
      });

    return () => {
      disposed = true;

      cleanupRuntime?.();

      gameRef.current = null;
      setRuntimeStatus('idle');

      if (containerRef.current) {
        containerRef.current.innerHTML = '';
      }
    };
  }, [enabled, payload]);

  return {
    containerRef,
    game: gameRef.current,
    runtimeStatus,
    runtimeError,
  };
}
