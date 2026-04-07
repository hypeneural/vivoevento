/**
 * Drift — Timer drift compensation for background tabs.
 *
 * Phase 0.3: When a tab is hidden (e.g. screensaver, TV menu, tab switch),
 * browsers throttle setTimeout/setInterval. On visibility restore, we
 * detect elapsed time and decide if an immediate advance is needed.
 *
 * Also handles heartbeat resync when drift is severe.
 */

/**
 * Check if the elapsed time since lastAdvanceAt exceeds the interval.
 * If so, the player should advance immediately to compensate.
 */
export function compensateDrift(
  lastAdvanceAt: number,
  intervalMs: number,
  now: number = Date.now(),
): boolean {
  if (lastAdvanceAt <= 0 || intervalMs <= 0) {
    return false;
  }

  const elapsed = now - lastAdvanceAt;
  return elapsed > intervalMs;
}

/**
 * Check if the elapsed time since the last heartbeat is severe enough
 * to warrant a full resync (not just a heartbeat).
 * Threshold: 2× the heartbeat interval.
 */
export function shouldForceResync(
  lastHeartbeatAt: number,
  heartbeatIntervalMs: number,
  now: number = Date.now(),
): boolean {
  if (lastHeartbeatAt <= 0 || heartbeatIntervalMs <= 0) {
    return false;
  }

  const elapsed = now - lastHeartbeatAt;
  return elapsed > heartbeatIntervalMs * 2;
}
