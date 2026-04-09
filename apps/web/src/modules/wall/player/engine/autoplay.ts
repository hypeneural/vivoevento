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
export const EVENT_VIDEO_STARTUP_DEADLINE_MS = 1_200;
export const EVENT_VIDEO_STALL_BUDGET_MS = 2_500;
export const DEFAULT_EVENT_VIDEO_RESUME_MODE = 'resume_if_same_item_else_restart' as const;
export const DEFAULT_EVENT_VIDEO_PLAYBACK_MODE = 'play_to_end_if_short_else_cap' as const;
export const DEFAULT_EVENT_VIDEO_MAX_SECONDS = 30;

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
    loop: false,
    preload: 'auto',
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

export interface EventVideoPolicyConfig {
  intervalMs?: number | null;
  playbackMode?: 'fixed_interval' | 'play_to_end' | 'play_to_end_if_short_else_cap' | null;
  maxSeconds?: number | null;
  resumeMode?: 'resume_if_same_item' | 'restart_from_zero' | 'resume_if_same_item_else_restart' | null;
}

export function resolveEventVideoCapMs(
  durationSeconds?: number | null,
  config?: EventVideoPolicyConfig | null,
): number | null {
  const playbackMode = config?.playbackMode ?? DEFAULT_EVENT_VIDEO_PLAYBACK_MODE;
  const maxSeconds = Math.max(1, Math.trunc(config?.maxSeconds ?? DEFAULT_EVENT_VIDEO_MAX_SECONDS));

  if (playbackMode === 'play_to_end') {
    return null;
  }

  if (playbackMode === 'fixed_interval') {
    const intervalMs = Math.max(1_000, Math.trunc(config?.intervalMs ?? maxSeconds * 1000));
    return intervalMs;
  }

  if (!Number.isFinite(durationSeconds)) {
    return maxSeconds * 1000;
  }

  const durationMs = Math.max(0, Math.round((durationSeconds as number) * 1000));
  if (durationMs > 0 && durationMs <= maxSeconds * 1000) {
    return null;
  }

  return maxSeconds * 1000;
}

export function resolveEventVideoResumeMode(
  config?: EventVideoPolicyConfig | null,
): typeof DEFAULT_EVENT_VIDEO_RESUME_MODE {
  const resumeMode = config?.resumeMode;

  if (
    resumeMode === 'resume_if_same_item'
    || resumeMode === 'restart_from_zero'
    || resumeMode === 'resume_if_same_item_else_restart'
  ) {
    return resumeMode;
  }

  return DEFAULT_EVENT_VIDEO_RESUME_MODE;
}
