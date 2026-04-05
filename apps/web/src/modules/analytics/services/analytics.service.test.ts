import { afterEach, describe, expect, it, vi } from 'vitest';

import { analyticsService } from './analytics.service';

describe('analytics.service', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('requests platform analytics with the selected filters', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({
        data: {
          filters: {
            period: '30d',
            date_from: '2026-03-03',
            date_to: '2026-04-01',
            comparison: {
              date_from: '2026-02-01',
              date_to: '2026-03-02',
            },
            organization_id: 7,
            client_id: 22,
            event_status: 'active',
            module: 'play',
          },
          summary: {},
          deltas: {},
          timelines: { media: [], traffic: [], play: [] },
          breakdowns: { modules: [], source_types: [], event_statuses: [] },
          rankings: { top_events: [] },
        },
      }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    );

    await analyticsService.getPlatform({
      period: '30d',
      organization_id: 7,
      client_id: 22,
      event_status: 'active',
      module: 'play',
    });

    const url = String(fetchSpy.mock.calls[0]?.[0] ?? '');

    expect(url).toContain('/analytics/platform');
    expect(url).toContain('organization_id=7');
    expect(url).toContain('client_id=22');
    expect(url).toContain('event_status=active');
    expect(url).toContain('module=play');
  });

  it('forwards client filters when searching remote event options', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({
        data: [],
        meta: {
          page: 1,
          per_page: 10,
          total: 0,
          last_page: 1,
          request_id: 'req_test',
        },
      }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    );

    await analyticsService.searchEvents('congresso', {
      organization_id: 9,
      client_id: 11,
      event_status: 'active',
      module: 'hub',
    });

    const url = String(fetchSpy.mock.calls[0]?.[0] ?? '');

    expect(url).toContain('/events');
    expect(url).toContain('search=congresso');
    expect(url).toContain('organization_id=9');
    expect(url).toContain('client_id=11');
    expect(url).toContain('status=active');
    expect(url).toContain('module=hub');
  });
});
