import type { PlayRuntimeAsset } from '@/lib/api-types';

export const PUZZLE_SOURCE_TEXTURE_KEY = 'puzzle-source';
export const PUZZLE_PARTICLE_TEXTURE_KEY = 'puzzle-particle-dot';
export const PUZZLE_PROMPT_TEXTURE_KEY = 'puzzle-prompt-touch';

export const PUZZLE_AUDIO_KEYS = {
  pickup: 'puzzle-sfx-pickup',
  hover: 'puzzle-sfx-hover',
  snap: 'puzzle-sfx-snap',
  error: 'puzzle-sfx-error',
  victory: 'puzzle-sfx-victory',
} as const;

export const PUZZLE_AUDIO_ASSETS = {
  [PUZZLE_AUDIO_KEYS.pickup]: '/assets/play/puzzle/audio/piece-pickup.wav',
  [PUZZLE_AUDIO_KEYS.hover]: '/assets/play/puzzle/audio/slot-hover.wav',
  [PUZZLE_AUDIO_KEYS.snap]: '/assets/play/puzzle/audio/slot-snap.wav',
  [PUZZLE_AUDIO_KEYS.error]: '/assets/play/puzzle/audio/placement-error.wav',
  [PUZZLE_AUDIO_KEYS.victory]: '/assets/play/puzzle/audio/puzzle-victory.wav',
} as const;

export const PUZZLE_UI_ASSETS = {
  promptTouch: '/assets/play/puzzle/prompts/touch-drag.svg',
  iconMoves: '/assets/play/puzzle/icons/moves.svg',
  iconCombo: '/assets/play/puzzle/icons/combo.svg',
  iconTimer: '/assets/play/puzzle/icons/timer.svg',
  particleSpark: '/assets/play/puzzle/particles/spark-dot.svg',
} as const;

export function isPuzzleCoverAsset(asset: PlayRuntimeAsset | null | undefined) {
  if (!asset?.url) {
    return false;
  }

  const mimeType = String(asset.mimeType ?? '').toLowerCase();

  return mimeType.startsWith('image/');
}

export function resolvePuzzleCoverAsset(assets: PlayRuntimeAsset[] | null | undefined) {
  return (assets ?? []).find((asset) => isPuzzleCoverAsset(asset)) ?? null;
}
