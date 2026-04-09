import { afterEach, describe, expect, it, vi } from 'vitest';

import { api, removeToken, setToken } from './api';
import { clearRealtimeSocketId, setRealtimeSocketId } from './realtime';

describe('api realtime transport characterization', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    clearRealtimeSocketId();
    removeToken();
  });

  it('forwards AbortSignal to fetch so TanStack Query cancellation can work when services pass the signal through', async () => {
    const signal = new AbortController().signal;
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ success: true, data: [] }), {
      status: 200,
      headers: {
        'Content-Type': 'application/json',
      },
    }));

    vi.stubGlobal('fetch', fetchMock);

    await api.get('/media/feed', { signal });

    expect(fetchMock).toHaveBeenCalledTimes(1);

    const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];

    expect(init.signal).toBe(signal);
  });

  it('automatically attaches X-Socket-ID to outgoing requests when a realtime socket is active', async () => {
    setToken('token-de-teste');
    setRealtimeSocketId('1234.5678');

    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ success: true, data: { id: 1 } }), {
      status: 200,
      headers: {
        'Content-Type': 'application/json',
      },
    }));

    vi.stubGlobal('fetch', fetchMock);

    await api.post('/media/1/approve');

    expect(fetchMock).toHaveBeenCalledTimes(1);

    const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    const headers = new Headers(init.headers);

    expect(headers.get('Authorization')).toBe('Bearer token-de-teste');
    expect(headers.get('X-Socket-ID')).toBe('1234.5678');
  });
});
