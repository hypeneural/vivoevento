/**
 * Motion — Utility to resolve effective transition based on reduced-motion preference.
 *
 * Phase 0.2: When prefers-reduced-motion is active, ALL animations are disabled:
 * - Transition = 'none'
 * - Ken Burns animations = disabled
 * - Neon pulse = disabled
 * - Toast/toast animations = disabled
 */

import type { WallTransition } from '../types';

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
