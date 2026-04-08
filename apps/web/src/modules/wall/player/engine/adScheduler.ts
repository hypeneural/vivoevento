/**
 * Ad Scheduler — Decides when to show ads between slideshow photos.
 *
 * Modes:
 * - 'disabled': never show ads
 * - 'by_photos': show ad every N photo advances
 * - 'by_minutes': show ad every N minutes
 *
 * Features:
 * - Anti-loop guard: after ad finishes, skip 1 tick to prevent back-to-back
 * - Cycles through ads in order (round-robin by position)
 * - Returns null when ads list is empty
 */

import type { WallAdItem, WallAdMode } from '../types';

export interface AdSchedulerState {
  mode: WallAdMode;
  frequency: number;           // N photos or N minutes
  photosSinceLastAd: number;
  lastAdPlayedAt: number | null;
  lastAdIndex: number;
  skipNextAdCheck: boolean;    // anti-loop guard
}

export function createAdSchedulerState(
  mode: WallAdMode = 'disabled',
  frequency: number = 5,
): AdSchedulerState {
  return {
    mode,
    frequency,
    photosSinceLastAd: 0,
    lastAdPlayedAt: null,
    lastAdIndex: -1,
    skipNextAdCheck: false,
  };
}

/**
 * Should we play an ad right now?
 */
export function shouldPlayAd(
  state: AdSchedulerState,
  adsCount: number,
  now: number = Date.now(),
): boolean {
  if (state.mode === 'disabled') return false;
  if (adsCount === 0) return false;
  if (state.skipNextAdCheck) return false;

  if (state.mode === 'by_photos') {
    return state.frequency > 0 && state.photosSinceLastAd >= state.frequency;
  }

  if (state.mode === 'by_minutes') {
    if (state.frequency <= 0) return false;
    if (state.lastAdPlayedAt === null) {
      // First ad after frequency minutes from start
      return true; // Schedule the first ad immediately after interval passes
    }
    const elapsedMs = now - state.lastAdPlayedAt;
    return elapsedMs >= state.frequency * 60 * 1000;
  }

  return false;
}

/**
 * Call after a photo advance (not an ad).
 */
export function markPhotoAdvanced(state: AdSchedulerState): AdSchedulerState {
  return {
    ...state,
    photosSinceLastAd: state.photosSinceLastAd + 1,
    skipNextAdCheck: false,
  };
}

/**
 * Call when an ad finishes playing.
 */
export function markAdPlayed(state: AdSchedulerState): AdSchedulerState {
  return {
    ...state,
    photosSinceLastAd: 0,
    lastAdPlayedAt: Date.now(),
    skipNextAdCheck: true,
  };
}

/**
 * Select the next ad in round-robin order.
 */
export function selectNextAd(
  ads: WallAdItem[],
  lastAdIndex: number,
): { ad: WallAdItem | null; nextIndex: number } {
  if (ads.length === 0) return { ad: null, nextIndex: -1 };

  const nextIndex = (lastAdIndex + 1) % ads.length;
  return { ad: ads[nextIndex], nextIndex };
}

/**
 * Update the scheduler state with new ad settings.
 */
export function updateAdSchedulerMode(
  state: AdSchedulerState,
  mode: WallAdMode,
  frequency: number,
): AdSchedulerState {
  return {
    ...state,
    mode,
    frequency,
  };
}
