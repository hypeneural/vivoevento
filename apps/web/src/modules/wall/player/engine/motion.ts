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
import type {
  WallPerformanceTier,
  WallTransition,
  WallTransitionFallbackReason,
} from '../types';
import type { WallMotionTokens } from '../themes/motion';
import {
  getWallTransitionDefinition,
  type WallTransitionContext,
} from './transition-registry';

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

export interface ResolvedLayoutTransition {
  requestedEffect: WallTransition;
  effect: WallTransition;
  fallbackReason: WallTransitionFallbackReason | null;
  variants: {
    initial: Record<string, number>;
    animate: Record<string, number>;
    exit: Record<string, number>;
  };
  transition: Transition;
}

export interface ResolvedOperationalTransitionEffect {
  requestedEffect: WallTransition;
  effect: WallTransition;
  fallbackReason: WallTransitionFallbackReason | null;
}

export function resolveOperationalTransitionEffect(
  transition: WallTransition | string | null | undefined,
  disableMotion: boolean,
  performanceTier: WallPerformanceTier = 'premium',
  prefersReducedMotion = disableMotion,
): ResolvedOperationalTransitionEffect {
  const requestedDefinition = getWallTransitionDefinition(transition ?? 'fade');
  const requestedEffect = requestedDefinition.id;
  const effectWasUnavailable = typeof transition === 'string' && requestedEffect !== transition;

  if (disableMotion) {
    const effect = requestedDefinition.reducedMotionFallback;

    return {
      requestedEffect,
      effect,
      fallbackReason: effect !== requestedEffect
        ? (prefersReducedMotion ? 'reduced_motion' : 'capability_tier')
        : null,
    };
  }

  if (
    performanceTier === 'performance'
    && requestedDefinition.performanceTierFallback
    && requestedDefinition.performanceTierFallback !== requestedEffect
  ) {
    return {
      requestedEffect,
      effect: requestedDefinition.performanceTierFallback,
      fallbackReason: 'capability_tier',
    };
  }

  return {
    requestedEffect,
    effect: requestedEffect,
    fallbackReason: effectWasUnavailable ? 'effect_unavailable' : null,
  };
}

export function resolveLayoutTransition(
  transition: WallTransition,
  tokens: Pick<WallMotionTokens, 'enter' | 'visualDuration'>,
  reducedMotion: boolean,
  performanceTier: WallPerformanceTier = 'premium',
  prefersReducedMotion = reducedMotion,
): ResolvedLayoutTransition {
  const operational = resolveOperationalTransitionEffect(
    transition,
    reducedMotion,
    performanceTier,
    prefersReducedMotion,
  );
  const resolvedDefinition = getWallTransitionDefinition(operational.effect);
  const context: WallTransitionContext = {
    tokens,
    reducedMotion,
  };

  return {
    requestedEffect: operational.requestedEffect,
    effect: resolvedDefinition.id,
    fallbackReason: operational.fallbackReason,
    variants: resolvedDefinition.buildVariants(context),
    transition: resolvedDefinition.buildTransition(context),
  };
}
