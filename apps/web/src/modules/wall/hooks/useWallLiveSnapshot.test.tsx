import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react';
import type { PropsWithChildren } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { ApiWallLiveSnapshotResponse } from '@/lib/api-types';

import { useWallLiveSnapshot } from './useWallLiveSnapshot';

const getEventWallLiveSnapshotMock = vi.fn();

vi.mock('../api', () => ({
  getEventWallLiveSnapshot: (...args: unknown[]) => getEventWallLiveSnapshotMock(...args),
}));

function makeLiveSnapshotResponse(overrides: Partial<ApiWallLiveSnapshotResponse> = {}): ApiWallLiveSnapshotResponse {
  return {
    wallStatus: 'live',
    wallStatusLabel: 'Ao vivo',
    layout: 'auto',
    transitionEffect: 'fade',
    currentPlayer: {
      playerInstanceId: 'player-alpha',
      healthStatus: 'healthy',
      runtimeStatus: 'playing',
      connectionStatus: 'connected',
      lastSeenAt: '2026-04-08T10:11:00Z',
    },
    currentItem: {
      id: 'media_10',
      previewUrl: 'https://cdn.example.com/thumbs/media-10.jpg',
      senderName: 'Juliana Ribeiro',
      senderKey: 'whatsapp:5511999990009',
      source: 'whatsapp',
      caption: 'Entrada da pista',
      layoutHint: 'cinematic',
      isFeatured: false,
      createdAt: '2026-04-08T10:10:00Z',
    },
    updatedAt: '2026-04-08T10:11:00Z',
    ...overrides,
  };
}

function createWrapper() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return function Wrapper({ children }: PropsWithChildren) {
    return (
      <QueryClientProvider client={queryClient}>
        {children}
      </QueryClientProvider>
    );
  };
}

describe('useWallLiveSnapshot', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('carrega o snapshot ao vivo do wall pelo eventId', async () => {
    getEventWallLiveSnapshotMock.mockResolvedValue(makeLiveSnapshotResponse());

    const { result } = renderHook(() => useWallLiveSnapshot('31'), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(result.current.data?.currentItem?.senderName).toBe('Juliana Ribeiro');
    });

    expect(getEventWallLiveSnapshotMock).toHaveBeenCalledWith('31');
  });

  it('nao dispara a query quando o eventId ainda nao existe', async () => {
    const { result } = renderHook(() => useWallLiveSnapshot(''), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(result.current.fetchStatus).toBe('idle');
    });

    expect(getEventWallLiveSnapshotMock).not.toHaveBeenCalled();
  });
});
