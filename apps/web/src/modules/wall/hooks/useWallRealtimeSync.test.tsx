import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react';
import type { PropsWithChildren } from 'react';
import { act } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { WALL_EVENT_NAMES } from '@eventovivo/shared-types/wall';

import { queryKeys } from '@/lib/query-client';

import { useWallRealtimeSync } from './useWallRealtimeSync';

const createWallManagerPusherMock = vi.fn();
const disconnectWallManagerPusherMock = vi.fn();

vi.mock('../realtime/pusher', () => ({
  createWallManagerPusher: (...args: unknown[]) => createWallManagerPusherMock(...args),
  disconnectWallManagerPusher: (...args: unknown[]) => disconnectWallManagerPusherMock(...args),
}));

function createWrapper(queryClient: QueryClient) {
  return function Wrapper({ children }: PropsWithChildren) {
    return (
      <QueryClientProvider client={queryClient}>
        {children}
      </QueryClientProvider>
    );
  };
}

function createFakePusher() {
  const connectionHandlers = new Map<string, Set<(payload: unknown) => void>>();
  const channelHandlers = new Map<string, Set<(payload: unknown) => void>>();

  const connection = {
    bind: vi.fn((eventName: string, handler: (payload: unknown) => void) => {
      const handlers = connectionHandlers.get(eventName) ?? new Set();
      handlers.add(handler);
      connectionHandlers.set(eventName, handlers);
    }),
    unbind: vi.fn((eventName: string, handler: (payload: unknown) => void) => {
      connectionHandlers.get(eventName)?.delete(handler);
    }),
  };

  const channel = {
    bind: vi.fn((eventName: string, handler: (payload: unknown) => void) => {
      const handlers = channelHandlers.get(eventName) ?? new Set();
      handlers.add(handler);
      channelHandlers.set(eventName, handlers);
    }),
    unbind: vi.fn((eventName: string, handler: (payload: unknown) => void) => {
      channelHandlers.get(eventName)?.delete(handler);
    }),
  };

  return {
    connection,
    subscribe: vi.fn(() => channel),
    unsubscribe: vi.fn(),
    emitStateChange(current: string) {
      const handlers = connectionHandlers.get('state_change') ?? new Set();

      handlers.forEach((handler) => handler({ current }));
    },
    emitChannelEvent(eventName: string, payload?: unknown) {
      const handlers = channelHandlers.get(eventName) ?? new Set();

      handlers.forEach((handler) => handler(payload));
    },
  };
}

describe('useWallRealtimeSync', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('fica offline quando nao existe client websocket configurado', async () => {
    createWallManagerPusherMock.mockReturnValue(null);
    const queryClient = new QueryClient({
      defaultOptions: {
        queries: {
          retry: false,
        },
      },
    });

    const { result } = renderHook(() => useWallRealtimeSync('31'), {
      wrapper: createWrapper(queryClient),
    });

    await waitFor(() => {
      expect(result.current).toBe('offline');
    });
  });

  it('invalida settings, diagnostics, insights e evento ao reconectar', async () => {
    const fakePusher = createFakePusher();
    createWallManagerPusherMock.mockReturnValue(fakePusher);

    const queryClient = new QueryClient({
      defaultOptions: {
        queries: {
          retry: false,
        },
      },
    });
    const invalidateQueriesSpy = vi.spyOn(queryClient, 'invalidateQueries');

    const { result, unmount } = renderHook(() => useWallRealtimeSync('31'), {
      wrapper: createWrapper(queryClient),
    });

    await waitFor(() => {
      expect(result.current).toBe('connecting');
    });

    act(() => {
      fakePusher.emitStateChange('connected');
    });

    await waitFor(() => {
      expect(result.current).toBe('connected');
    });

    await waitFor(() => {
      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: queryKeys.wall.settings('31') });
      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: queryKeys.wall.diagnostics('31') });
      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: queryKeys.wall.insights('31') });
      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: queryKeys.events.detail('31') });
    });

    unmount();

    expect(fakePusher.unsubscribe).toHaveBeenCalledWith('private-event.31.wall');
    expect(disconnectWallManagerPusherMock).toHaveBeenCalled();
  });

  it('invalida apenas diagnostico e insights quando chega evento tecnico do wall', async () => {
    const fakePusher = createFakePusher();
    createWallManagerPusherMock.mockReturnValue(fakePusher);

    const queryClient = new QueryClient({
      defaultOptions: {
        queries: {
          retry: false,
        },
      },
    });
    const invalidateQueriesSpy = vi.spyOn(queryClient, 'invalidateQueries');

    renderHook(() => useWallRealtimeSync('31'), {
      wrapper: createWrapper(queryClient),
    });

    invalidateQueriesSpy.mockClear();

    act(() => {
      fakePusher.emitChannelEvent(WALL_EVENT_NAMES.diagnosticsUpdated);
    });

    await waitFor(() => {
      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: queryKeys.wall.diagnostics('31') });
      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: queryKeys.wall.insights('31') });
    });

    expect(invalidateQueriesSpy).not.toHaveBeenCalledWith({ queryKey: queryKeys.wall.settings('31') });
    expect(invalidateQueriesSpy).not.toHaveBeenCalledWith({ queryKey: queryKeys.events.detail('31') });
  });
});
