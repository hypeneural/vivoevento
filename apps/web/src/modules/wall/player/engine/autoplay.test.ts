/**
 * Tests for engine/autoplay.ts (Phase 0.4)
 */

import { describe, it, expect } from 'vitest';
import {
  AD_VIDEO_SAFETY_TIMEOUT_MS,
  resolveEventVideoAttrs,
  resolveAdVideoAttrs,
} from '../engine/autoplay';

describe('resolveEventVideoAttrs', () => {
  it('returns muted=true for reliable autoplay', () => {
    expect(resolveEventVideoAttrs().muted).toBe(true);
  });

  it('returns autoPlay=true', () => {
    expect(resolveEventVideoAttrs().autoPlay).toBe(true);
  });

  it('returns playsInline=true', () => {
    expect(resolveEventVideoAttrs().playsInline).toBe(true);
  });

  it('returns loop=true for event slideshow videos', () => {
    expect(resolveEventVideoAttrs().loop).toBe(true);
  });
});

describe('resolveAdVideoAttrs', () => {
  it('returns muted=true for reliable autoplay', () => {
    expect(resolveAdVideoAttrs().muted).toBe(true);
  });

  it('returns loop=false so onended fires', () => {
    expect(resolveAdVideoAttrs().loop).toBe(false);
  });

  it('returns preload=auto for aggressive buffering', () => {
    expect(resolveAdVideoAttrs().preload).toBe('auto');
  });
});

describe('AD_VIDEO_SAFETY_TIMEOUT_MS', () => {
  it('equals 5 minutes in milliseconds', () => {
    expect(AD_VIDEO_SAFETY_TIMEOUT_MS).toBe(300_000);
  });
});
