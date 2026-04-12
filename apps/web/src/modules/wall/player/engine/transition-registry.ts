import type { Transition } from 'framer-motion';

import type { WallTransition } from '../types';
import type { WallMotionTokens } from '../themes/motion';

type LayoutTransitionVariants = {
  initial: Record<string, number>;
  animate: Record<string, number>;
  exit: Record<string, number>;
};

export interface WallTransitionContext {
  tokens: Pick<WallMotionTokens, 'enter' | 'visualDuration'>;
  reducedMotion: boolean;
}

export interface WallTransitionDefinition {
  id: WallTransition;
  scope: 'single' | 'board';
  buildVariants: (context: WallTransitionContext) => LayoutTransitionVariants;
  buildTransition: (context: WallTransitionContext) => Transition;
  reducedMotionFallback: WallTransition;
  performanceTierFallback?: WallTransition | null;
}

const DEFAULT_WALL_TRANSITION: WallTransition = 'fade';
const REDUCED_MOTION_FALLBACK: WallTransition = 'none';
export const DEFAULT_RANDOM_WALL_TRANSITION_POOL: readonly WallTransition[] = [
  'fade',
  'slide',
  'zoom',
  'flip',
  'lift-fade',
  'cross-zoom',
  'swipe-up',
] as const;

function buildTimedTransition(
  context: WallTransitionContext,
  effect: WallTransitionDefinition['id'],
): Transition {
  if (effect === 'none') {
    return { duration: 0 };
  }

  return {
    duration: context.tokens.visualDuration,
    ease: context.tokens.enter.ease,
  };
}

const WALL_TRANSITION_DEFINITIONS = [
  {
    id: 'fade',
    scope: 'single',
    buildVariants: () => ({
      initial: { opacity: 0, scale: 0.996 },
      animate: { opacity: 1, scale: 1 },
      exit: { opacity: 0 },
    }),
    buildTransition: (context) => buildTimedTransition(context, 'fade'),
    reducedMotionFallback: REDUCED_MOTION_FALLBACK,
    performanceTierFallback: null,
  },
  {
    id: 'slide',
    scope: 'single',
    buildVariants: () => ({
      initial: { opacity: 0, x: 60 },
      animate: { opacity: 1, x: 0 },
      exit: { opacity: 0, x: -60 },
    }),
    buildTransition: (context) => buildTimedTransition(context, 'slide'),
    reducedMotionFallback: REDUCED_MOTION_FALLBACK,
    performanceTierFallback: null,
  },
  {
    id: 'zoom',
    scope: 'single',
    buildVariants: () => ({
      initial: { opacity: 0, scale: 0.92 },
      animate: { opacity: 1, scale: 1 },
      exit: { opacity: 0, scale: 1.08 },
    }),
    buildTransition: (context) => buildTimedTransition(context, 'zoom'),
    reducedMotionFallback: REDUCED_MOTION_FALLBACK,
    performanceTierFallback: null,
  },
  {
    id: 'flip',
    scope: 'single',
    buildVariants: () => ({
      initial: { opacity: 0, rotateY: 90 },
      animate: { opacity: 1, rotateY: 0 },
      exit: { opacity: 0, rotateY: -90 },
    }),
    buildTransition: (context) => buildTimedTransition(context, 'flip'),
    reducedMotionFallback: REDUCED_MOTION_FALLBACK,
    performanceTierFallback: 'fade',
  },
  {
    id: 'lift-fade',
    scope: 'single',
    buildVariants: () => ({
      initial: { opacity: 0, y: 28, scale: 0.985 },
      animate: { opacity: 1, y: 0, scale: 1 },
      exit: { opacity: 0, y: -20, scale: 1.01 },
    }),
    buildTransition: (context) => buildTimedTransition(context, 'lift-fade'),
    reducedMotionFallback: REDUCED_MOTION_FALLBACK,
    performanceTierFallback: null,
  },
  {
    id: 'cross-zoom',
    scope: 'single',
    buildVariants: () => ({
      initial: { opacity: 0, scale: 1.065 },
      animate: { opacity: 1, scale: 1 },
      exit: { opacity: 0, scale: 0.94 },
    }),
    buildTransition: (context) => buildTimedTransition(context, 'cross-zoom'),
    reducedMotionFallback: REDUCED_MOTION_FALLBACK,
    performanceTierFallback: null,
  },
  {
    id: 'swipe-up',
    scope: 'single',
    buildVariants: () => ({
      initial: { opacity: 0, y: 72 },
      animate: { opacity: 1, y: 0 },
      exit: { opacity: 0, y: -72 },
    }),
    buildTransition: (context) => buildTimedTransition(context, 'swipe-up'),
    reducedMotionFallback: REDUCED_MOTION_FALLBACK,
    performanceTierFallback: null,
  },
  {
    id: 'none',
    scope: 'single',
    buildVariants: () => ({
      initial: { opacity: 1 },
      animate: { opacity: 1 },
      exit: { opacity: 1 },
    }),
    buildTransition: (context) => buildTimedTransition(context, 'none'),
    reducedMotionFallback: REDUCED_MOTION_FALLBACK,
    performanceTierFallback: null,
  },
] as const satisfies readonly WallTransitionDefinition[];

const WALL_TRANSITION_MAP = new Map<string, WallTransitionDefinition>(
  WALL_TRANSITION_DEFINITIONS.map((definition) => [definition.id, definition]),
);

export function listWallTransitionDefinitions(): WallTransitionDefinition[] {
  return [...WALL_TRANSITION_DEFINITIONS];
}

export function getWallTransitionDefinition(
  transition: WallTransition | string,
): WallTransitionDefinition {
  return WALL_TRANSITION_MAP.get(transition) ?? WALL_TRANSITION_MAP.get(DEFAULT_WALL_TRANSITION)!;
}

export function sanitizeWallTransitionPool(
  pool?: readonly (WallTransition | string | null | undefined)[] | null,
): WallTransition[] {
  if (!pool || pool.length === 0) {
    return [];
  }

  const sanitized: WallTransition[] = [];

  for (const effect of pool) {
    if (typeof effect !== 'string') {
      continue;
    }

    const resolved = getWallTransitionDefinition(effect).id;

    if (resolved === 'none' || resolved !== effect) {
      continue;
    }

    if (sanitized.includes(resolved)) {
      continue;
    }

    sanitized.push(resolved);
  }

  return sanitized;
}
