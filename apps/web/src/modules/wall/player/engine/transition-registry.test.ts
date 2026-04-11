import { describe, expect, it } from 'vitest';

import {
  getWallTransitionDefinition,
  listWallTransitionDefinitions,
} from './transition-registry';
import type { WallMotionTokens } from '../themes/motion';

const tokens: WallMotionTokens = {
  enter: {
    duration: 0.42,
    ease: 'easeOut',
  },
  exit: {
    duration: 0.32,
    ease: 'easeInOut',
  },
  burst: {
    duration: 0.24,
    ease: 'easeOut',
  },
  drift: {
    duration: 18,
    ease: 'linear',
  },
  visualDuration: 0.42,
  reducedMotion: 'user',
};

describe('transition registry', () => {
  it('registers every safe slideshow effect as a single-slide definition', () => {
    const definitions = listWallTransitionDefinitions();

    expect(definitions.map((definition) => definition.id)).toEqual([
      'fade',
      'slide',
      'zoom',
      'flip',
      'lift-fade',
      'cross-zoom',
      'swipe-up',
      'none',
    ]);
    expect(definitions.every((definition) => definition.scope === 'single')).toBe(true);
  });

  it('provides reduced-motion fallback metadata for every registered effect', () => {
    const definitions = listWallTransitionDefinitions();

    expect(definitions.every((definition) => definition.reducedMotionFallback === 'none')).toBe(true);
  });

  it('falls back to the fade definition when the requested effect is unknown', () => {
    const resolved = getWallTransitionDefinition('unknown-effect');

    expect(resolved.id).toBe('fade');
    expect(resolved.scope).toBe('single');
  });

  it('builds transition variants and timing from the registry definition', () => {
    const resolved = getWallTransitionDefinition('slide');

    expect(resolved.buildVariants({ tokens, reducedMotion: false })).toMatchObject({
      initial: { opacity: 0, x: 60 },
      animate: { opacity: 1, x: 0 },
      exit: { opacity: 0, x: -60 },
    });
    expect(resolved.buildTransition({ tokens, reducedMotion: false })).toMatchObject({
      duration: tokens.visualDuration,
      ease: tokens.enter.ease,
    });
  });

  it('defines safe premium-ready variants for the new slideshow effects', () => {
    expect(getWallTransitionDefinition('lift-fade').buildVariants({ tokens, reducedMotion: false })).toMatchObject({
      initial: { opacity: 0, y: 28, scale: 0.985 },
      animate: { opacity: 1, y: 0, scale: 1 },
      exit: { opacity: 0, y: -20, scale: 1.01 },
    });

    expect(getWallTransitionDefinition('cross-zoom').buildVariants({ tokens, reducedMotion: false })).toMatchObject({
      initial: { opacity: 0, scale: 1.065 },
      animate: { opacity: 1, scale: 1 },
      exit: { opacity: 0, scale: 0.94 },
    });

    expect(getWallTransitionDefinition('swipe-up').buildVariants({ tokens, reducedMotion: false })).toMatchObject({
      initial: { opacity: 0, y: 72 },
      animate: { opacity: 1, y: 0 },
      exit: { opacity: 0, y: -72 },
    });
  });
});
