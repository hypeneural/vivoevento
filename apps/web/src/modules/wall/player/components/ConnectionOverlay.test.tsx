import { describe, expect, it, vi } from 'vitest';

import { shouldShowConnectionOverlay } from './ConnectionOverlay';

describe('ConnectionOverlay', () => {
  it('hides the disconnected warning when the player synced recently', () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-04-07T01:00:00Z'));

    expect(
      shouldShowConnectionOverlay('disconnected', false, '2026-04-07T00:59:30Z'),
    ).toBe(false);
  });

  it('shows the disconnected warning when the last sync is stale', () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-04-07T01:00:00Z'));

    expect(
      shouldShowConnectionOverlay('disconnected', false, '2026-04-07T00:57:00Z'),
    ).toBe(true);
  });

  it('shows the syncing indicator while a fresh sync is running', () => {
    expect(
      shouldShowConnectionOverlay('connected', true, '2026-04-07T00:59:30Z'),
    ).toBe(true);
  });
});
