import type Phaser from 'phaser';

import type { PlayRuntimeAsset } from '@/lib/api-types';
import type { GameResumeState, NormalizedGameResult, PlayerIdentity } from '@/modules/play/types';

export type GameRuntimeMove = {
  moveType: string;
  payload?: Record<string, unknown>;
  occurredAt?: string;
};

export type GameBootPayload<TSettings = Record<string, unknown>> = {
  eventGameId: number;
  sessionUuid: string;
  gameKey: string;
  sessionSeed?: string;
  player: PlayerIdentity;
  assets: PlayRuntimeAsset[];
  settings: TSettings;
  restore?: GameResumeState | null;
};

export type CreateGameParams<TSettings = Record<string, unknown>> = {
  container: HTMLDivElement;
  payload: GameBootPayload<TSettings>;
  onReady?: () => void;
  onError?: (error: Error | string) => void;
  onProgress?: (data: Record<string, unknown>) => void;
  onMove?: (move: GameRuntimeMove) => void;
  onFinish?: (result: NormalizedGameResult) => void;
};

export interface EventovivoPlayableGame<TSettings = Record<string, unknown>> {
  key: string;
  boot: (params: CreateGameParams<TSettings>) => Phaser.Game;
  destroy?: (game: Phaser.Game) => void;
}
