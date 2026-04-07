/**
 * Tests for engine/motion.ts (Phase 0.2)
 */

import { describe, it, expect } from 'vitest';
import {
  resolveEffectiveTransition,
  resolveKenBurnsAnimationClass,
  shouldShowNeonPulse,
} from '../engine/motion';

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
