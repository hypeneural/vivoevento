import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { ApiError, api, removeToken } from './api';

describe('api client', () => {
  const fetchMock = vi.fn();

  beforeEach(() => {
    vi.stubGlobal('fetch', fetchMock);
    localStorage.clear();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    removeToken();
    vi.restoreAllMocks();
  });

  it('unwraps regular Laravel JSON responses', async () => {
    fetchMock.mockResolvedValue(new Response(JSON.stringify({
      success: true,
      data: {
        ok: true,
      },
      meta: {
        request_id: 'req_test',
      },
    }), {
      status: 200,
      headers: {
        'Content-Type': 'application/json',
      },
    }));

    await expect(api.get<{ ok: boolean }>('/health/live')).resolves.toEqual({ ok: true });
  });

  it('throws a clear error when an API request receives HTML instead of JSON', async () => {
    fetchMock.mockResolvedValue(new Response('<!doctype html><html><body>fallback</body></html>', {
      status: 200,
      headers: {
        'Content-Type': 'text/html; charset=utf-8',
      },
    }));

    await expect(api.get('/dashboard/stats')).rejects.toMatchObject<ApiError>({
      name: 'ApiError',
      status: 502,
      body: expect.objectContaining({
        message: expect.stringContaining('non-JSON response'),
        response_preview: expect.stringContaining('<!doctype html>'),
      }),
    });
  });
});
