/**
 * Tests for engine/autoplay.ts (Phase 0.4)
 */

import { describe, it, expect } from 'vitest';
import {
  AD_VIDEO_SAFETY_TIMEOUT_MS,
  resolveEventVideoAttrs,
  resolveAdVideoAttrs,
  resolveEventVideoCapMs,
  resolveEventVideoResumeMode,
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

  it('returns loop=false so the reducer can decide the exit cause', () => {
    expect(resolveEventVideoAttrs().loop).toBe(false);
  });

  it('returns preload=auto for the controlled video lane', () => {
    expect(resolveEventVideoAttrs().preload).toBe('auto');
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

describe('resolveEventVideoCapMs', () => {
  it('returns null when the wall policy is play_to_end', () => {
    expect(resolveEventVideoCapMs(90, {
      playbackMode: 'play_to_end',
      maxSeconds: 20,
    })).toBeNull();
  });

  it('uses interval_ms when the wall policy is fixed_interval', () => {
    expect(resolveEventVideoCapMs(90, {
      playbackMode: 'fixed_interval',
      intervalMs: 8_000,
      maxSeconds: 20,
    })).toBe(8_000);
  });

  it('lets short videos reach the end when they are under the cap', () => {
    expect(resolveEventVideoCapMs(12, {
      playbackMode: 'play_to_end_if_short_else_cap',
      maxSeconds: 20,
    })).toBeNull();
  });

  it('caps long videos at the configured max seconds', () => {
    expect(resolveEventVideoCapMs(45, {
      playbackMode: 'play_to_end_if_short_else_cap',
      maxSeconds: 20,
    })).toBe(20_000);
  });
});

describe('resolveEventVideoResumeMode', () => {
  it('returns the configured resume mode when valid', () => {
    expect(resolveEventVideoResumeMode({
      resumeMode: 'restart_from_zero',
    })).toBe('restart_from_zero');
  });

  it('falls back to the default resume mode when missing', () => {
    expect(resolveEventVideoResumeMode({})).toBe('resume_if_same_item_else_restart');
  });
});
