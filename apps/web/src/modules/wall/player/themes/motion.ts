import type { MotionConfigProps } from 'framer-motion';

import {
  WALL_MOTION_BURST_DURATION,
  WALL_MOTION_DRIFT_DURATION_SECONDS,
  WALL_MOTION_PUZZLE_BURST_DURATION,
  WALL_MOTION_PUZZLE_DRIFT_DURATION_SECONDS,
  WALL_MOTION_VISUAL_DURATION_BOARD,
  WALL_MOTION_VISUAL_DURATION_PUZZLE,
  WALL_MOTION_VISUAL_DURATION_SINGLE,
} from '../design/tokens';
import type { WallLayout, WallReducedMotionSetting } from '../types';

type WallMotionEase = 'linear' | 'easeOut' | 'easeInOut';

interface WallMotionChannel {
  duration: number;
  ease: WallMotionEase;
}

export interface WallMotionTokens {
  enter: WallMotionChannel;
  exit: WallMotionChannel;
  burst: WallMotionChannel;
  drift: WallMotionChannel;
  visualDuration: number;
  reducedMotion: WallReducedMotionSetting;
}

const SINGLE_MOTION_TOKENS: WallMotionTokens = {
  enter: {
    duration: WALL_MOTION_VISUAL_DURATION_SINGLE,
    ease: 'easeOut',
  },
  exit: {
    duration: 0.32,
    ease: 'easeInOut',
  },
  burst: {
    duration: WALL_MOTION_BURST_DURATION,
    ease: 'easeOut',
  },
  drift: {
    duration: WALL_MOTION_DRIFT_DURATION_SECONDS,
    ease: 'linear',
  },
  visualDuration: WALL_MOTION_VISUAL_DURATION_SINGLE,
  reducedMotion: 'user',
};

const BOARD_MOTION_TOKENS: WallMotionTokens = {
  enter: {
    duration: WALL_MOTION_VISUAL_DURATION_BOARD,
    ease: 'easeOut',
  },
  exit: {
    duration: 0.28,
    ease: 'easeInOut',
  },
  burst: {
    duration: WALL_MOTION_BURST_DURATION,
    ease: 'easeOut',
  },
  drift: {
    duration: WALL_MOTION_DRIFT_DURATION_SECONDS,
    ease: 'linear',
  },
  visualDuration: WALL_MOTION_VISUAL_DURATION_BOARD,
  reducedMotion: 'user',
};

const MOTION_BY_LAYOUT: Record<WallLayout, WallMotionTokens> = {
  auto: SINGLE_MOTION_TOKENS,
  fullscreen: SINGLE_MOTION_TOKENS,
  polaroid: SINGLE_MOTION_TOKENS,
  split: {
    ...SINGLE_MOTION_TOKENS,
    visualDuration: 0.4,
  },
  cinematic: {
    ...SINGLE_MOTION_TOKENS,
    visualDuration: 0.46,
  },
  kenburns: {
    ...SINGLE_MOTION_TOKENS,
    drift: {
      duration: 20,
      ease: 'linear',
    },
  },
  spotlight: SINGLE_MOTION_TOKENS,
  gallery: SINGLE_MOTION_TOKENS,
  carousel: BOARD_MOTION_TOKENS,
  mosaic: BOARD_MOTION_TOKENS,
  grid: BOARD_MOTION_TOKENS,
  puzzle: {
    enter: {
      duration: WALL_MOTION_VISUAL_DURATION_PUZZLE,
      ease: 'easeOut',
    },
    exit: {
      duration: 0.3,
      ease: 'easeInOut',
    },
    burst: {
      duration: WALL_MOTION_PUZZLE_BURST_DURATION,
      ease: 'easeOut',
    },
    drift: {
      duration: WALL_MOTION_PUZZLE_DRIFT_DURATION_SECONDS,
      ease: 'linear',
    },
    visualDuration: WALL_MOTION_VISUAL_DURATION_PUZZLE,
    reducedMotion: 'user',
  },
};

export function getWallLayoutMotionTokens(layout: WallLayout): WallMotionTokens {
  return MOTION_BY_LAYOUT[layout] ?? SINGLE_MOTION_TOKENS;
}

export function resolveWallMotionConfig(
  tokens: WallMotionTokens,
  reducedEffects: boolean,
): Pick<MotionConfigProps, 'reducedMotion' | 'transition'> {
  if (reducedEffects) {
    return {
      reducedMotion: 'always',
      transition: {
        duration: 0,
      },
    };
  }

  return {
    reducedMotion: tokens.reducedMotion,
    transition: {
      duration: tokens.visualDuration,
      ease: tokens.enter.ease,
    },
  };
}
