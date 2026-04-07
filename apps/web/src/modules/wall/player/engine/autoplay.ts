/**
 * Autoplay — Video autoplay policy utilities.
 *
 * Phase 0.4: Ensures all video elements in the wall player comply
 * with browser autoplay policies:
 *
 * 1. ALL videos MUST be muted for reliable autoplay
 * 2. Event videos: muted + autoPlay + playsInline + loop
 * 3. Ad videos (future): muted + autoPlay + playsInline + NO loop + onended
 * 4. Safety timeout for ad videos: 5 min max (fallback if onended never fires)
 */

/**
 * Default maximum duration for an ad video before forcing advance.
 * Acts as a safety net if onended never fires (e.g., video decode error).
 */
export const AD_VIDEO_SAFETY_TIMEOUT_MS = 5 * 60 * 1000; // 5 minutes

/**
 * Resolve autoplay attributes for a video element depending on context.
 */
export interface VideoAutoplayAttrs {
  autoPlay: boolean;
  muted: boolean;
  playsInline: boolean;
  loop: boolean;
  preload: 'none' | 'metadata' | 'auto';
}

export function resolveEventVideoAttrs(): VideoAutoplayAttrs {
  return {
    autoPlay: true,
    muted: true,
    playsInline: true,
    loop: true,
    preload: 'metadata',
  };
}

export function resolveAdVideoAttrs(): VideoAutoplayAttrs {
  return {
    autoPlay: true,
    muted: true,
    playsInline: true,
    loop: false, // Ad videos should NOT loop — we need onended to fire
    preload: 'auto', // Pre-buffer the ad video aggressively
  };
}
