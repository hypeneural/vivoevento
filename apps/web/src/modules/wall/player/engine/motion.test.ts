/**
 * Tests for engine/motion.ts (Phase 0.2)
 */

import { describe, it, expect } from 'vitest';
import {
  resolveLayoutTransition,
  resolveEffectiveTransition,
  resolveKenBurnsAnimationClass,
  resolveOperationalTransitionEffect,
  shouldShowNeonPulse,
} from '../engine/motion';
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

describe('resolveEffectiveTransition', () => {
  it('returns "none" when reducedMotion is true, regardless of input', () => {
    expect(resolveEffectiveTransition('fade', true)).toBe('none');
    expect(resolveEffectiveTransition('slide', true)).toBe('none');
    expect(resolveEffectiveTransition('zoom', true)).toBe('none');
    expect(resolveEffectiveTransition('flip', true)).toBe('none');
    expect(resolveEffectiveTransition('none', true)).toBe('none');
  });

  it('returns the original transition when reducedMotion is false', () => {
    expect(resolveEffectiveTransition('fade', false)).toBe('fade');
    expect(resolveEffectiveTransition('slide', false)).toBe('slide');
    expect(resolveEffectiveTransition('zoom', false)).toBe('zoom');
    expect(resolveEffectiveTransition('flip', false)).toBe('flip');
    expect(resolveEffectiveTransition('none', false)).toBe('none');
  });
});

describe('resolveKenBurnsAnimationClass', () => {
  it('returns undefined when reducedMotion is true', () => {
    expect(resolveKenBurnsAnimationClass(0, true)).toBeUndefined();
    expect(resolveKenBurnsAnimationClass(1, true)).toBeUndefined();
    expect(resolveKenBurnsAnimationClass(99, true)).toBeUndefined();
  });

  it('cycles through 4 animation classes when reducedMotion is false', () => {
    expect(resolveKenBurnsAnimationClass(0, false)).toBe('kb-zoom-in');
    expect(resolveKenBurnsAnimationClass(1, false)).toBe('kb-zoom-out');
    expect(resolveKenBurnsAnimationClass(2, false)).toBe('kb-pan-left');
    expect(resolveKenBurnsAnimationClass(3, false)).toBe('kb-pan-right');
    expect(resolveKenBurnsAnimationClass(4, false)).toBe('kb-zoom-in'); // wraps
  });
});

describe('shouldShowNeonPulse', () => {
  it('returns false when reducedMotion', () => {
    expect(shouldShowNeonPulse(true)).toBe(false);
  });

  it('returns true when not reducedMotion', () => {
    expect(shouldShowNeonPulse(false)).toBe(true);
  });
});

describe('resolveLayoutTransition', () => {
  it('uses theme motion tokens to build a visible transition when reducedMotion is false', () => {
    const resolved = resolveLayoutTransition('slide', tokens, false);

    expect(resolved.effect).toBe('slide');
    expect(resolved.transition).toMatchObject({
      duration: tokens.visualDuration,
      ease: tokens.enter.ease,
    });
    expect(resolved.variants.initial).toMatchObject({ opacity: 0, x: 60 });
  });

  it('forces transition effect none and zero duration when reducedMotion is true', () => {
    const resolved = resolveLayoutTransition('zoom', tokens, true);

    expect(resolved.effect).toBe('none');
    expect(resolved.transition).toMatchObject({ duration: 0 });
    expect(resolved.variants.initial).toMatchObject({ opacity: 1 });
  });

  it('falls back to fade when an unsupported transition effect reaches the resolver', () => {
    const resolved = resolveLayoutTransition('unsupported-effect' as never, tokens, false);

    expect(resolved.effect).toBe('fade');
    expect(resolved.fallbackReason).toBe('effect_unavailable');
    expect(resolved.transition).toMatchObject({
      duration: tokens.visualDuration,
      ease: tokens.enter.ease,
    });
    expect(resolved.variants.initial).toMatchObject({ opacity: 0, scale: 0.996 });
  });

  it('falls back to a cheaper safe effect on performance tier for heavier transitions', () => {
    const resolved = resolveLayoutTransition('flip', tokens, false, 'performance');

    expect(resolved.effect).toBe('fade');
    expect(resolved.fallbackReason).toBe('capability_tier');
    expect(resolved.variants.initial).toMatchObject({ opacity: 0, scale: 0.996 });
  });
});

describe('resolveOperationalTransitionEffect', () => {
  it('prioritizes reduced motion over other transition fallbacks', () => {
    const resolved = resolveOperationalTransitionEffect('flip', true, 'performance');

    expect(resolved.requestedEffect).toBe('flip');
    expect(resolved.effect).toBe('none');
    expect(resolved.fallbackReason).toBe('reduced_motion');
  });
});
