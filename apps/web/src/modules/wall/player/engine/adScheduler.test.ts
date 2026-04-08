/**
 * Tests for adScheduler — ad scheduling logic.
 */
import { describe, expect, it } from 'vitest';
import {
  createAdSchedulerState,
  shouldPlayAd,
  markPhotoAdvanced,
  markAdPlayed,
  selectNextAd,
  updateAdSchedulerMode,
} from '../engine/adScheduler';
import type { WallAdItem } from '../types';

function makeAd(id: number, type: 'image' | 'video' = 'image', duration = 10): WallAdItem {
  return {
    id,
    url: `https://cdn.example.com/ad-${id}.jpg`,
    media_type: type,
    duration_seconds: duration,
    position: id,
  };
}

describe('shouldPlayAd', () => {
  it('returns false when mode is disabled', () => {
    const state = createAdSchedulerState('disabled', 5);
    expect(shouldPlayAd(state, 3)).toBe(false);
  });

  it('returns false when ads count is 0', () => {
    const state = createAdSchedulerState('by_photos', 5);
    expect(shouldPlayAd(state, 0)).toBe(false);
  });

  it('returns false when skipNextAdCheck is true', () => {
    let state = createAdSchedulerState('by_photos', 1);
    state = markAdPlayed(state);
    expect(shouldPlayAd(state, 3)).toBe(false);
  });

  it('returns true after N photos in by_photos mode', () => {
    let state = createAdSchedulerState('by_photos', 3);
    state = markPhotoAdvanced(state); // 1
    state = markPhotoAdvanced(state); // 2
    expect(shouldPlayAd(state, 3)).toBe(false);
    state = markPhotoAdvanced(state); // 3
    expect(shouldPlayAd(state, 3)).toBe(true);
  });

  it('returns false before N photos in by_photos mode', () => {
    let state = createAdSchedulerState('by_photos', 5);
    state = markPhotoAdvanced(state);
    state = markPhotoAdvanced(state);
    expect(shouldPlayAd(state, 3)).toBe(false);
  });

  it('returns true after N minutes in by_minutes mode', () => {
    let state = createAdSchedulerState('by_minutes', 2);
    state = { ...state, lastAdPlayedAt: Date.now() - 3 * 60 * 1000 }; // 3 min ago
    expect(shouldPlayAd(state, 3)).toBe(true);
  });

  it('returns false before N minutes in by_minutes mode', () => {
    let state = createAdSchedulerState('by_minutes', 5);
    state = { ...state, lastAdPlayedAt: Date.now() - 2 * 60 * 1000 }; // 2 min ago
    expect(shouldPlayAd(state, 3)).toBe(false);
  });

  it('returns true on first check in by_minutes mode (no last played)', () => {
    const state = createAdSchedulerState('by_minutes', 1);
    expect(shouldPlayAd(state, 3)).toBe(true);
  });
});

describe('markPhotoAdvanced', () => {
  it('increments photo counter', () => {
    let state = createAdSchedulerState('by_photos', 5);
    state = markPhotoAdvanced(state);
    expect(state.photosSinceLastAd).toBe(1);
    state = markPhotoAdvanced(state);
    expect(state.photosSinceLastAd).toBe(2);
  });

  it('clears skipNextAdCheck flag', () => {
    let state = createAdSchedulerState('by_photos', 5);
    state = markAdPlayed(state); // sets skipNextAdCheck=true
    state = markPhotoAdvanced(state);
    expect(state.skipNextAdCheck).toBe(false);
  });
});

describe('markAdPlayed', () => {
  it('resets photo counter', () => {
    let state = createAdSchedulerState('by_photos', 3);
    state = markPhotoAdvanced(state);
    state = markPhotoAdvanced(state);
    state = markAdPlayed(state);
    expect(state.photosSinceLastAd).toBe(0);
  });

  it('sets skipNextAdCheck to true', () => {
    let state = createAdSchedulerState('by_photos', 3);
    state = markAdPlayed(state);
    expect(state.skipNextAdCheck).toBe(true);
  });

  it('sets lastAdPlayedAt', () => {
    let state = createAdSchedulerState('by_photos', 3);
    state = markAdPlayed(state);
    expect(state.lastAdPlayedAt).toBeDefined();
    expect(typeof state.lastAdPlayedAt).toBe('number');
  });
});

describe('selectNextAd', () => {
  it('returns null when ads array is empty', () => {
    const { ad, nextIndex } = selectNextAd([], -1);
    expect(ad).toBeNull();
    expect(nextIndex).toBe(-1);
  });

  it('cycles through ads in order', () => {
    const ads = [makeAd(1), makeAd(2), makeAd(3)];

    const r1 = selectNextAd(ads, -1);
    expect(r1.ad?.id).toBe(1);
    expect(r1.nextIndex).toBe(0);

    const r2 = selectNextAd(ads, 0);
    expect(r2.ad?.id).toBe(2);
    expect(r2.nextIndex).toBe(1);

    const r3 = selectNextAd(ads, 1);
    expect(r3.ad?.id).toBe(3);
    expect(r3.nextIndex).toBe(2);

    // Wraps around
    const r4 = selectNextAd(ads, 2);
    expect(r4.ad?.id).toBe(1);
    expect(r4.nextIndex).toBe(0);
  });
});

describe('updateAdSchedulerMode', () => {
  it('updates mode and frequency', () => {
    const state = createAdSchedulerState('disabled', 5);
    const updated = updateAdSchedulerMode(state, 'by_photos', 10);
    expect(updated.mode).toBe('by_photos');
    expect(updated.frequency).toBe(10);
  });

  it('preserves other state fields', () => {
    let state = createAdSchedulerState('by_photos', 5);
    state = markPhotoAdvanced(state);
    state = markPhotoAdvanced(state);

    const updated = updateAdSchedulerMode(state, 'by_minutes', 2);
    expect(updated.photosSinceLastAd).toBe(2);
  });
});
