import { describe, expect, it } from 'vitest';

import { wallQueryOptions } from './wall-query-options';

describe('wallQueryOptions', () => {
  it('keeps manager-facing settings conservative and warms simulation previews without focus/reconnect churn', () => {
    expect(wallQueryOptions.event.staleTime).toBe(60 * 1000);
    expect(wallQueryOptions.event.refetchOnReconnect).toBe(false);
    expect(wallQueryOptions.settings.staleTime).toBe(60 * 1000);
    expect(wallQueryOptions.settings.refetchOnReconnect).toBe(false);
    expect(wallQueryOptions.diagnostics.staleTime).toBe(15 * 1000);
    expect(wallQueryOptions.diagnostics.refetchOnReconnect).toBe(false);
    expect(wallQueryOptions.simulation.staleTime).toBe(15 * 1000);
    expect(wallQueryOptions.simulation.refetchOnWindowFocus).toBe(false);
    expect(wallQueryOptions.simulation.refetchOnReconnect).toBe(false);
  });

  it('reuses previous snapshots where visual continuity matters', () => {
    const simulation = { summary: { queue_items: 1 } } as any;
    const insights = { totals: { queued: 2 } } as any;

    expect(wallQueryOptions.simulation.placeholderData(simulation)).toBe(simulation);
    expect(wallQueryOptions.insights.placeholderData(insights)).toBe(insights);
  });
});
