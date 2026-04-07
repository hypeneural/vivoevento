/**
 * Tests for engine/drift.ts (Phase 0.3)
 */

import { describe, it, expect } from 'vitest';
import { compensateDrift, shouldForceResync } from '../engine/drift';

describe('compensateDrift', () => {
  const now = 1_700_000_000_000;
  const intervalMs = 8_000;

  it('returns true when elapsed > intervalMs', () => {
    const lastAdvance = now - 10_000; // 10s ago, interval is 8s
    expect(compensateDrift(lastAdvance, intervalMs, now)).toBe(true);
  });

  it('returns false when elapsed <= intervalMs', () => {
    const lastAdvance = now - 5_000; // 5s ago, interval is 8s
    expect(compensateDrift(lastAdvance, intervalMs, now)).toBe(false);
  });

  it('returns false when elapsed === intervalMs (edge case)', () => {
    const lastAdvance = now - intervalMs;
    expect(compensateDrift(lastAdvance, intervalMs, now)).toBe(false);
  });

  it('returns false when lastAdvanceAt is 0', () => {
    expect(compensateDrift(0, intervalMs, now)).toBe(false);
  });

  it('returns false when intervalMs is 0', () => {
    expect(compensateDrift(now - 5000, 0, now)).toBe(false);
  });

  it('handles large drift (60s+ background)', () => {
    const lastAdvance = now - 120_000; // 2 minutes ago
    expect(compensateDrift(lastAdvance, intervalMs, now)).toBe(true);
  });
});

describe('shouldForceResync', () => {
  const now = 1_700_000_000_000;
  const heartbeatInterval = 20_000;

  it('returns true when elapsed > 2× heartbeat interval', () => {
    const lastHeartbeat = now - 50_000; // 50s ago, threshold is 40s
    expect(shouldForceResync(lastHeartbeat, heartbeatInterval, now)).toBe(true);
  });

  it('returns false when elapsed <= 2× heartbeat interval', () => {
    const lastHeartbeat = now - 30_000; // 30s ago, threshold is 40s
    expect(shouldForceResync(lastHeartbeat, heartbeatInterval, now)).toBe(false);
  });

  it('returns false when lastHeartbeatAt is 0', () => {
    expect(shouldForceResync(0, heartbeatInterval, now)).toBe(false);
  });

  it('returns false when heartbeatIntervalMs is 0', () => {
    expect(shouldForceResync(now - 5000, 0, now)).toBe(false);
  });
});
