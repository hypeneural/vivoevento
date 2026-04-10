/**
 * Motion — Utility to resolve effective transition based on reduced-motion preference.
 *
 * Phase 0.2: When prefers-reduced-motion is active, ALL animations are disabled:
 * - Transition = 'none'
 * - Ken Burns animations = disabled
 * - Neon pulse = disabled
 * - Toast/toast animations = disabled
 */

import type { Transition } from 'framer-motion';
import type { WallTransition } from '../types';
import type { WallMotionTokens } from '../themes/motion';

/**
 * When reduced-motion is active, force transition to 'none'
 * regardless of the user's configuration choice.
 */
export function resolveEffectiveTransition(
  transition: WallTransition,
  reducedMotion: boolean,
): WallTransition {
  if (reducedMotion) {
    return 'none';
  }
  return transition;
}

/**
 * Resolve the Ken Burns animation class to apply.
 * Returns undefined when reduced motion is active.
 */
const KB_CLASSES = ['kb-zoom-in', 'kb-zoom-out', 'kb-pan-left', 'kb-pan-right'] as const;

export function resolveKenBurnsAnimationClass(
  index: number,
  reducedMotion: boolean,
): string | undefined {
  if (reducedMotion) {
    return undefined;
  }
  return KB_CLASSES[index % KB_CLASSES.length];
}

/**
 * Check if neon pulse animation should be shown.
 */
export function shouldShowNeonPulse(reducedMotion: boolean): boolean {
  return !reducedMotion;
}

type LayoutTransitionVariants = {
  initial: Record<string, number>;
  animate: Record<string, number>;
  exit: Record<string, number>;
};

export interface ResolvedLayoutTransition {
  effect: WallTransition;
  variants: LayoutTransitionVariants;
  transition: Transition;
}

export function resolveLayoutTransition(
  transition: WallTransition,
  tokens: Pick<WallMotionTokens, 'enter' | 'visualDuration'>,
  reducedMotion: boolean,
): ResolvedLayoutTransition {
  const effect = resolveEffectiveTransition(transition, reducedMotion);
  const resolvedTransition: Transition = effect === 'none'
    ? { duration: 0 }
    : { duration: tokens.visualDuration, ease: tokens.enter.ease };

  switch (effect) {
    case 'slide':
      return {
        effect,
        variants: {
          initial: { opacity: 0, x: 60 },
          animate: { opacity: 1, x: 0 },
          exit: { opacity: 0, x: -60 },
        },
        transition: resolvedTransition,
      };
    case 'zoom':
      return {
        effect,
        variants: {
          initial: { opacity: 0, scale: 0.92 },
          animate: { opacity: 1, scale: 1 },
          exit: { opacity: 0, scale: 1.08 },
        },
        transition: resolvedTransition,
      };
    case 'flip':
      return {
        effect,
        variants: {
          initial: { opacity: 0, rotateY: 90 },
          animate: { opacity: 1, rotateY: 0 },
          exit: { opacity: 0, rotateY: -90 },
        },
        transition: resolvedTransition,
      };
    case 'none':
      return {
        effect,
        variants: {
          initial: { opacity: 1 },
          animate: { opacity: 1 },
          exit: { opacity: 1 },
        },
        transition: resolvedTransition,
      };
    case 'fade':
    default:
      return {
        effect,
        variants: {
          initial: { opacity: 0, scale: 0.996 },
          animate: { opacity: 1, scale: 1 },
          exit: { opacity: 0 },
        },
        transition: resolvedTransition,
      };
  }
}
